# Sub-Task 04b: Adaptive E-Rapor Engine — Backend & Approval Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bangun fondasi backend murni (skema DB, generator narasi, 4 Action approval workflow) untuk E-Rapor Engine — headless, tanpa route/controller/view sama sekali, diuji lewat Feature test yang memanggil Action langsung.

**Architecture:** Laravel 11 domain-oriented (`app/Domains/Akademik/`). Reuse Universal Workflow Engine yang sudah ada (`app/Domains/Workflow/`, dipakai modul Pengadaan Sarpras) untuk approval 2-tahap (Waka Kurikulum → Kepala Sekolah), dengan pola manual untuk tolak-kembali-ke-draft dan efek kunci nilai (engine tidak native mendukung keduanya — modul Pengadaan pun menanganinya manual di luar engine, plan ini mereplikasi pola yang sama persis).

**Tech Stack:** Laravel 11, Pest, MySQL, `barryvdh/laravel-dompdf` (tidak dipakai di sub-task ini), Spatie Laravel-Permission.

**Konteks proyek yang perlu diketahui sebelum mulai:**
- Codebase ini adalah SaaS multi-tenant sekolah. Isolasi tenant ditegakkan lewat trait `App\Models\Concerns\BelongsToTenant` (auto-apply global scope `App\Models\Scopes\TenantScope` — filter otomatis berdasar `lembaga_id` aktor yang login). Proyek ini punya riwayat bug IDOR cross-tenant berulang di modul lain — setiap model baru WAJIB pakai `BelongsToTenant` dan setiap Action WAJIB diuji untuk skenario lintas-tenant.
- Sub-Task 04a (SELESAI, sudah di-commit di branch yang sama) menyediakan fondasi domain penilaian: `App\Domains\Akademik\Models\{KomponenPenilaian,Asesmen,NilaiSiswa}`, `App\Domains\Akademik\Enums\JenisAsesmen`, `App\Domains\Akademik\Services\RaporCalculationService`, dan Action-Action di `app/Domains/Akademik/Actions/Penilaian/`. Sub-task ini (04b) membangun DI ATAS fondasi itu, termasuk memodifikasi 2 file dari 04a (lihat Task 2 dan Task 5).
- `KomponenPenilaian` berperan sebagai Tujuan Pembelajaran (TP) — field `kode`/`deskripsi`/`kktp` (teks kualitatif, BUKAN angka) persis itu, dan `bobot` (integer 1-100) adalah bobot pembagi rata-rata tertimbang antar-TP dalam satu mata pelajaran (dipakai `RaporCalculationService`) — BUKAN ambang kelulusan. Sub-task ini menambah kolom BARU `kktp_minimal` (integer 0-100, terpisah dari keduanya) sebagai ambang numerik.
- Baca spec lengkap sebelum mulai: [`.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md) — plan ini mengimplementasikan spec itu task-by-task, tapi spec berisi rasional/alasan desain yang tidak diulang di sini.

## Global Constraints

- **Tidak ada route/controller/view apa pun** di sub-task ini — murni Action + Service + Model + test. UI dibangun di sub-task terpisah (04c) setelah ini selesai & direview.
- **Tidak mengubah/menghapus** definisi workflow `PENGADAAN_SARPRAS` yang sudah ada di `WorkflowDefinitionSeeder` — hanya menambah definisi baru `RAPOR_SEMESTER`.
- **Tidak mengubah** file apa pun di `app/Domains/Workflow/` (engine itu sendiri) — sub-task ini murni konsumen baru dari engine yang sudah ada.
- Setiap Action yang membungkus mutasi multi-baris HARUS dibungkus `DB::transaction()`.
- Setiap file baru pakai `declare(strict_types=1);` dan `final class`/`final readonly class` (kecuali Model — Eloquent Model di codebase ini konsisten TIDAK pakai `final`, ikuti pola `KomponenPenilaian`/`Asesmen` dari 04a).
- **Regression wajib**: seluruh test existing dari 04a (`KomponenPenilaianCrudTest`, `KomponenPenilaianControllerTest`, `AsesmenControllerTest`, dan lainnya) HARUS tetap hijau tanpa perubahan assertion di sepanjang plan ini — perubahan pada file 04a (Task 2, Task 5) harus backward-compatible.
- Jalankan `php artisan test` FULL SUITE hanya SATU KALI, di Task 7 (final), dan hanya setelah bertanya dulu ke user apakah mau dijalankan. Selama Task 1-6, jalankan HANYA test yang scoped ke file yang disentuh task tsb.
- Kalau eksekusi ini didelegasikan ke subagent (mis. lewat `subagent-driven-development`): jangan dispatch subagent apa pun (termasuk final whole-branch review) di model tier paling mahal/premium — pakai model standar/mid-tier dengan reasoning effort tinggi.

---

### Task 1: Migrasi Skema, Model, Enum, Factory

**Files:**
- Create: `database/migrations/2026_08_19_160000_create_pengajuan_rapor_table.php`
- Create: `database/migrations/2026_08_19_160100_create_catatan_wali_kelas_table.php`
- Create: `database/migrations/2026_08_19_160200_add_kktp_minimal_to_komponen_penilaian_table.php`
- Create: `app/Domains/Akademik/Enums/StatusPengajuanRapor.php`
- Create: `app/Domains/Akademik/Models/PengajuanRapor.php`
- Create: `app/Domains/Akademik/Models/CatatanWaliKelas.php`
- Create: `database/factories/PengajuanRaporFactory.php`
- Create: `database/factories/CatatanWaliKelasFactory.php`
- Modify: `app/Domains/Akademik/Models/KomponenPenilaian.php` (tambah `kktp_minimal` ke `$fillable`)
- Test: `tests/Unit/Models/PengajuanRaporTest.php`, `tests/Unit/Models/CatatanWaliKelasTest.php`

**Interfaces:**
- Consumes: `App\Models\Concerns\BelongsToTenant`, `App\Models\{Kelas,Lembaga,Semester,Siswa,User}` (existing, tidak berubah), `App\Domains\Workflow\Models\ApprovalRequest` (existing, dari engine).
- Produces: `App\Domains\Akademik\Models\PengajuanRapor` (fillable: `lembaga_id, kelas_id, semester_id, status, diajukan_oleh, diajukan_pada, diverifikasi_oleh, diverifikasi_pada, disetujui_oleh, disetujui_pada, catatan_revisi, tanggal_rapor`; method `approvalRequest(): MorphOne`), `App\Domains\Akademik\Models\CatatanWaliKelas` (fillable: `lembaga_id, siswa_id, semester_id, catatan_sikap, catatan_perkembangan, tinggi_badan_cm, berat_badan_kg, lingkar_kepala_cm, ekstrakurikuler, prestasi, pkl_info, keterangan_kenaikan`), `App\Domains\Akademik\Enums\StatusPengajuanRapor` (cases: `Draft, Diajukan, Diverifikasi, Disetujui, Ditolak`) — dipakai semua task berikutnya.

- [ ] **Step 1: Buat migrasi `pengajuan_rapor`**

`database/migrations/2026_08_19_160000_create_pengajuan_rapor_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_rapor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diajukan_pada')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('catatan_revisi')->nullable();
            $table->date('tanggal_rapor')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'semester_id']);
            $table->index(['lembaga_id', 'semester_id', 'status'], 'idx_pengajuan_rapor_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_rapor');
    }
};
```

- [ ] **Step 2: Buat migrasi `catatan_wali_kelas`**

`database/migrations/2026_08_19_160100_create_catatan_wali_kelas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_wali_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lembaga_id')->constrained('lembaga')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semester')->cascadeOnDelete();
            $table->text('catatan_sikap')->nullable();
            $table->text('catatan_perkembangan')->nullable();
            $table->decimal('tinggi_badan_cm', 5, 1)->nullable();
            $table->decimal('berat_badan_kg', 5, 1)->nullable();
            $table->decimal('lingkar_kepala_cm', 5, 1)->nullable();
            $table->json('ekstrakurikuler')->nullable();
            $table->json('prestasi')->nullable();
            $table->json('pkl_info')->nullable();
            $table->string('keterangan_kenaikan', 50)->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_wali_kelas');
    }
};
```

- [ ] **Step 3: Buat migrasi tambah kolom `kktp_minimal`**

`database/migrations/2026_08_19_160200_add_kktp_minimal_to_komponen_penilaian_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->unsignedTinyInteger('kktp_minimal')->nullable()->after('kktp');
        });
    }

    public function down(): void
    {
        Schema::table('komponen_penilaian', function (Blueprint $table) {
            $table->dropColumn('kktp_minimal');
        });
    }
};
```

- [ ] **Step 4: Jalankan migrasi**

```bash
php artisan migrate
```

Expected: 3 migrasi baru berjalan tanpa error (`2026_08_19_160000_create_pengajuan_rapor_table`, `2026_08_19_160100_create_catatan_wali_kelas_table`, `2026_08_19_160200_add_kktp_minimal_to_komponen_penilaian_table`).

- [ ] **Step 5: Tambah `kktp_minimal` ke `$fillable` di `KomponenPenilaian`**

Di `app/Domains/Akademik/Models/KomponenPenilaian.php`, ganti baris:
```php
    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp'];
```
menjadi:
```php
    protected $fillable = ['mata_pelajaran_id', 'semester_id', 'lembaga_id', 'kode', 'deskripsi', 'bobot', 'kktp', 'kktp_minimal'];
```

- [ ] **Step 6: Buat enum `StatusPengajuanRapor`**

`app/Domains/Akademik/Enums/StatusPengajuanRapor.php`:

```php
<?php

namespace App\Domains\Akademik\Enums;

enum StatusPengajuanRapor: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Diverifikasi = 'diverifikasi';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::Diverifikasi => 'Diverifikasi',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }
}
```

- [ ] **Step 7: Buat model `PengajuanRapor`**

`app/Domains/Akademik/Models/PengajuanRapor.php`:

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Database\Factories\PengajuanRaporFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PengajuanRapor extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'pengajuan_rapor';

    protected $fillable = [
        'lembaga_id', 'kelas_id', 'semester_id', 'status',
        'diajukan_oleh', 'diajukan_pada',
        'diverifikasi_oleh', 'diverifikasi_pada',
        'disetujui_oleh', 'disetujui_pada',
        'catatan_revisi', 'tanggal_rapor',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuanRapor::class,
            'diajukan_pada' => 'datetime',
            'diverifikasi_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'tanggal_rapor' => 'date',
        ];
    }

    protected static function newFactory(): PengajuanRaporFactory
    {
        return PengajuanRaporFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $pengajuanRapor) {
            if (empty($pengajuanRapor->lembaga_id)) {
                $pengajuanRapor->lembaga_id = Kelas::withoutGlobalScopes()
                    ->findOrFail($pengajuanRapor->kelas_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }
}
```

- [ ] **Step 8: Buat model `CatatanWaliKelas`**

`app/Domains/Akademik/Models/CatatanWaliKelas.php`:

```php
<?php

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use Database\Factories\CatatanWaliKelasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanWaliKelas extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'catatan_wali_kelas';

    protected $fillable = [
        'lembaga_id', 'siswa_id', 'semester_id',
        'catatan_sikap', 'catatan_perkembangan',
        'tinggi_badan_cm', 'berat_badan_kg', 'lingkar_kepala_cm',
        'ekstrakurikuler', 'prestasi', 'pkl_info', 'keterangan_kenaikan',
    ];

    protected function casts(): array
    {
        return [
            'tinggi_badan_cm' => 'decimal:1',
            'berat_badan_kg' => 'decimal:1',
            'lingkar_kepala_cm' => 'decimal:1',
            'ekstrakurikuler' => 'array',
            'prestasi' => 'array',
            'pkl_info' => 'array',
        ];
    }

    protected static function newFactory(): CatatanWaliKelasFactory
    {
        return CatatanWaliKelasFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $catatanWaliKelas) {
            if (empty($catatanWaliKelas->lembaga_id)) {
                $catatanWaliKelas->lembaga_id = Siswa::withoutGlobalScopes()
                    ->findOrFail($catatanWaliKelas->siswa_id)
                    ->lembaga_id;
            }
        });
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
```

- [ ] **Step 9: Buat factory `PengajuanRaporFactory`**

`database/factories/PengajuanRaporFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengajuanRaporFactory extends Factory
{
    protected $model = PengajuanRapor::class;

    public function definition(): array
    {
        return [
            'kelas_id' => Kelas::factory(),
            'semester_id' => Semester::factory(),
            'status' => StatusPengajuanRapor::Draft,
        ];
    }
}
```

- [ ] **Step 10: Buat factory `CatatanWaliKelasFactory`**

`database/factories/CatatanWaliKelasFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatatanWaliKelasFactory extends Factory
{
    protected $model = CatatanWaliKelas::class;

    public function definition(): array
    {
        return [
            'siswa_id' => Siswa::factory(),
            'semester_id' => Semester::factory(),
            'catatan_sikap' => $this->faker->sentence(10),
        ];
    }
}
```

- [ ] **Step 11: Tulis test model dasar (memastikan `lembaga_id` auto-derive dan `approvalRequest()` relasi benar)**

`tests/Unit/Models/PengajuanRaporTest.php`:

```php
<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('derives lembaga_id from kelas on create', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);

    $pengajuan = PengajuanRapor::factory()->create(['kelas_id' => $kelas->id, 'semester_id' => $semester->id]);

    expect($pengajuan->lembaga_id)->toBe($lembaga->id);
    expect($pengajuan->status)->toBe(StatusPengajuanRapor::Draft);
});

it('links to an ApprovalRequest via the approvalRequest morphOne relation', function () {
    $pengajuan = PengajuanRapor::factory()->create();

    // WorkflowDefinition tidak punya factory di codebase ini (dicek: `App\Domains\Workflow\Models\WorkflowDefinition`
    // pakai trait HasFactory tapi tanpa override newFactory(), dan tidak ada database/factories/WorkflowDefinitionFactory.php)
    // - pakai ::create() langsung, jangan panggil ::factory().
    $definisi = \App\Domains\Workflow\Models\WorkflowDefinition::create([
        'code' => 'TEST_PENGAJUAN_RAPOR_MODEL',
        'nama_workflow' => 'Test Workflow',
        'is_active' => true,
    ]);

    ApprovalRequest::create([
        'workflow_definition_id' => $definisi->id,
        'approvable_type' => $pengajuan->getMorphClass(),
        'approvable_id' => $pengajuan->id,
        'status' => \App\Domains\Workflow\Enums\ApprovalStatus::Pending,
    ]);

    expect($pengajuan->fresh()->approvalRequest)->not->toBeNull();
    expect($pengajuan->fresh()->approvalRequest->approvable_id)->toBe($pengajuan->id);
});
```

- [ ] **Step 12: Tulis test model `CatatanWaliKelas`**

`tests/Unit/Models/CatatanWaliKelasTest.php`:

```php
<?php

use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\Yayasan;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('derives lembaga_id from siswa on create and casts json columns to array', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

    $catatan = CatatanWaliKelas::factory()->create([
        'siswa_id' => $siswa->id,
        'ekstrakurikuler' => [['nama' => 'Pramuka', 'predikat' => 'A']],
        'tinggi_badan_cm' => 110.5,
    ]);

    expect($catatan->lembaga_id)->toBe($lembaga->id);
    expect($catatan->ekstrakurikuler)->toBe([['nama' => 'Pramuka', 'predikat' => 'A']]);
    expect((float) $catatan->tinggi_badan_cm)->toBe(110.5);
});
```

- [ ] **Step 13: Jalankan test**

```bash
php artisan test tests/Unit/Models/PengajuanRaporTest.php tests/Unit/Models/CatatanWaliKelasTest.php
```

Expected: semua PASS.

- [ ] **Step 14: Commit**

```bash
git add database/migrations/2026_08_19_160000_create_pengajuan_rapor_table.php database/migrations/2026_08_19_160100_create_catatan_wali_kelas_table.php database/migrations/2026_08_19_160200_add_kktp_minimal_to_komponen_penilaian_table.php app/Domains/Akademik/Enums/StatusPengajuanRapor.php app/Domains/Akademik/Models/PengajuanRapor.php app/Domains/Akademik/Models/CatatanWaliKelas.php app/Domains/Akademik/Models/KomponenPenilaian.php database/factories/PengajuanRaporFactory.php database/factories/CatatanWaliKelasFactory.php tests/Unit/Models/PengajuanRaporTest.php tests/Unit/Models/CatatanWaliKelasTest.php
git commit -m "feat(akademik): skema, model, dan factory PengajuanRapor & CatatanWaliKelas"
```

---

### Task 2: Perluasan `kktp_minimal` ke Action/DTO/FormRequest 04a (Backward-Compatible)

**Files:**
- Modify: `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- Modify: `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`
- Modify: `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php`
- Modify: `database/seeders/KomponenPenilaianSeeder.php`
- Test: `tests/Feature/Admin/KomponenPenilaianCrudTest.php`, `tests/Feature/Guru/KomponenPenilaianControllerTest.php` (existing dari 04a, TIDAK diubah isinya — regression net)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\KomponenPenilaian` (dari Task 1, sudah punya kolom+fillable `kktp_minimal`).
- Produces: `KomponenPenilaianData`/`UpdateKomponenPenilaianData` dengan property tambahan `?int $kktpMinimal` — dipakai `CapaianKompetensiGenerator` (Task 3) lewat `KomponenPenilaian->kktp_minimal` langsung (bukan lewat DTO ini), jadi task berikutnya TIDAK bergantung pada DTO ini secara langsung, hanya pada kolom DB-nya.

**PENTING — file-file ini SUDAH ADA dari Sub-Task 04a (sebelum plan ini dijalankan). Baca isi file yang sebenarnya di repo sebelum mengedit — kalau isinya berbeda dari yang diasumsikan di bawah, LAPORKAN (jangan dipaksa tetap edit), karena berarti ada perubahan yang belum diketahui plan ini.**

- [ ] **Step 1: Tambah `kktpMinimal` ke `KomponenPenilaianData`**

Di `app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php`, isi saat ini:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public int $mataPelajaranId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
        );
    }
}
```

Ganti jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class KomponenPenilaianData
{
    public function __construct(
        public int $mataPelajaranId,
        public int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: (int) $data['mata_pelajaran_id'],
            semesterId: (int) $data['semester_id'],
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : 10,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
        );
    }
}
```

- [ ] **Step 2: Tambah `kktpMinimal` ke `UpdateKomponenPenilaianData`**

Di `app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php`, isi saat ini:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?int $mataPelajaranId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: isset($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
        );
    }
}
```

Ganti jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class UpdateKomponenPenilaianData
{
    public function __construct(
        public ?int $mataPelajaranId,
        public ?int $semesterId,
        public ?string $kode,
        public string $deskripsi,
        public ?int $bobot,
        public ?string $kktp,
        public ?int $kktpMinimal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            mataPelajaranId: isset($data['mata_pelajaran_id']) ? (int) $data['mata_pelajaran_id'] : null,
            semesterId: isset($data['semester_id']) ? (int) $data['semester_id'] : null,
            kode: $data['kode'] ?? null,
            deskripsi: $data['deskripsi'],
            bobot: isset($data['bobot']) ? (int) $data['bobot'] : null,
            kktp: $data['kktp'] ?? null,
            kktpMinimal: isset($data['kktp_minimal']) ? (int) $data['kktp_minimal'] : null,
        );
    }
}
```

- [ ] **Step 3: Sertakan `kktp_minimal` di `CreateKomponenPenilaianAction`**

Di `app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php`, ganti blok:

```php
        return KomponenPenilaian::create([
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'semester_id' => $data->semesterId,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
        ]);
```

menjadi:

```php
        return KomponenPenilaian::create([
            'mata_pelajaran_id' => $data->mataPelajaranId,
            'semester_id' => $data->semesterId,
            'kode' => $data->kode,
            'deskripsi' => $data->deskripsi,
            'bobot' => $data->bobot,
            'kktp' => $data->kktp,
            'kktp_minimal' => $data->kktpMinimal,
        ]);
```

- [ ] **Step 4: Sertakan `kktp_minimal` di `UpdateKomponenPenilaianAction`**

Di `app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php`, ganti blok:

```php
        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->save();
```

menjadi:

```php
        $komponen->kode = $data->kode;
        $komponen->deskripsi = $data->deskripsi;
        $komponen->bobot = $newBobot;
        $komponen->kktp = $data->kktp;
        $komponen->kktp_minimal = $data->kktpMinimal;
        $komponen->save();
```

- [ ] **Step 5: Tambah rule `kktp_minimal` ke 4 FormRequest**

Di masing-masing 4 file berikut, cari baris `'kktp' => ['nullable', 'string'],` di dalam method `rules()`, dan tambahkan SATU baris baru persis setelahnya: `'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],`

- `app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php`
- `app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php`
- `app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php`
- `app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php`

Contoh hasil akhir untuk `StoreKomponenPenilaianRequest.php` (3 file lain analog, hanya bagian `rules()` yang berubah, `authorize()` dan `toDTO()` TIDAK berubah):

```php
    public function rules(): array
    {
        return [
            'mata_pelajaran_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer'],
            'kode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['required', 'string'],
            'bobot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'kktp' => ['nullable', 'string'],
            'kktp_minimal' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
```

- [ ] **Step 6: Update `KomponenPenilaianSeeder` — isi `kktp_minimal` untuk TP yang di-seed**

Di `database/seeders/KomponenPenilaianSeeder.php`, method `seedSmpKomponen()` dan `seedGenericKomponen()` masing-masing punya array kedua argumen `firstOrCreate(...)` berisi `['deskripsi' => ..., 'bobot' => ..., 'kktp' => ...]`. Tambahkan `'kktp_minimal' => 75` di tiap array itu. Contoh untuk `seedSmpKomponen()` (2 baris `KomponenPenilaian::firstOrCreate` untuk `$mtk`, 1 untuk `$ipa`):

```php
    private function seedSmpKomponen(Lembaga $smp, Semester $semester): void
    {
        $mtk = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Matematika')->first();
        $ipa = MataPelajaran::where('lembaga_id', $smp->id)->where('nama', 'Ilmu Pengetahuan Alam (IPA)')->first();

        if ($mtk) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mtk->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.1'],
                ['deskripsi' => 'Peserta didik dapat menyelesaikan operasi aritmetika pada bilangan bulat dan pecahan.', 'bobot' => 50, 'kktp' => 'Minimal 75% benar', 'kktp_minimal' => 75]
            );
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mtk->id, 'semester_id' => $semester->id, 'kode' => 'TP.1.2'],
                ['deskripsi' => 'Peserta didik mendeskripsikan dan mengekspresikan relasi serta fungsi dengan representasi grafik.', 'bobot' => 50, 'kktp' => 'Mampu menggambar grafik linier', 'kktp_minimal' => 75]
            );
        }

        if ($ipa) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $ipa->id, 'semester_id' => $semester->id, 'kode' => 'TP.IPA.1'],
                ['deskripsi' => 'Peserta didik memahami besaran pokok dan besaran turunan dalam satuan internasional.', 'bobot' => 100, 'kktp' => 'Tepat menggunakan alat ukur', 'kktp_minimal' => 75]
            );
        }
    }

    private function seedGenericKomponen(Lembaga $lembaga, Semester $semester): void
    {
        $mapelList = MataPelajaran::where('lembaga_id', $lembaga->id)->get();

        foreach ($mapelList as $mapel) {
            KomponenPenilaian::firstOrCreate(
                ['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kode' => 'TP.1'],
                ['deskripsi' => "Tujuan Pembelajaran Dasar untuk mata pelajaran {$mapel->nama}.", 'bobot' => 100, 'kktp' => 'Tercapai Sesuai Kriteria', 'kktp_minimal' => 75]
            );
        }
    }
```

(`run()` method di atas kedua private method ini TIDAK berubah.)

- [ ] **Step 7: Jalankan test regresi**

```bash
php artisan test tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Unit/KomponenPenilaianSeederTest.php
```

Expected: semua PASS tanpa perubahan assertion — field baru harus benar-benar opsional dan tidak mengubah perilaku existing.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/DataTransferObjects/KomponenPenilaianData.php app/Domains/Akademik/DataTransferObjects/UpdateKomponenPenilaianData.php app/Domains/Akademik/Actions/Penilaian/CreateKomponenPenilaianAction.php app/Domains/Akademik/Actions/Penilaian/UpdateKomponenPenilaianAction.php app/Http/Requests/Akademik/StoreKomponenPenilaianRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianRequest.php app/Http/Requests/Akademik/StoreKomponenPenilaianSendiriRequest.php app/Http/Requests/Akademik/UpdateKomponenPenilaianSendiriRequest.php database/seeders/KomponenPenilaianSeeder.php
git commit -m "feat(akademik): tambah kktp_minimal ke DTO/Action/FormRequest Komponen Penilaian (04a)"
```

---

### Task 3: `CapaianKompetensiGenerator`

**Files:**
- Create: `app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`
- Test: `tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\{KomponenPenilaian,Asesmen,NilaiSiswa}` (04a, dengan `kktp_minimal` dari Task 2), `App\Models\{Siswa,MataPelajaran,Semester}`.
- Produces: `CapaianKompetensiGenerator::generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array{tertinggi: ?string, terendah: ?string}` — TIDAK dipakai task lain di plan ini (dipakai sub-task 04c/04d nanti).

- [ ] **Step 1: Tulis test (TDD — harus FAIL dulu karena class belum ada)**

`tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php`:

```php
<?php

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\CapaianKompetensiGenerator;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;

function siapkanSiswaMapelSemester(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    return compact('siswa', 'mapel', 'semester', 'asesmen');
}

it('generates a positive sentence when the highest-scoring TP meets its kktp_minimal', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'operasi bilangan bulat', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam operasi bilangan bulat.');
    expect($hasil['terendah'])->toBeNull();
});

it('generates a needs-guidance sentence when the lowest-scoring TP is below its kktp_minimal', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'relasi dan fungsi', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 60]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBeNull();
    expect($hasil['terendah'])->toBe('Perlu bimbingan dan pendampingan dalam relasi dan fungsi.');
});

it('generates both sentences when there are at least two TP spanning both conditions', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponenTinggi = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP kuat', 'kktp_minimal' => 75]);
    $komponenRendah = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP lemah', 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenTinggi->id, 'nilai_angka' => 95]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenRendah->id, 'nilai_angka' => 50]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam TP kuat.');
    expect($hasil['terendah'])->toBe('Perlu bimbingan dan pendampingan dalam TP lemah.');
});

it('returns null for both when no TP has any nilai at all', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester] = siapkanSiswaMapelSemester();
    KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBeNull();
    expect($hasil['terendah'])->toBeNull();
});

it('falls back to a default 75 threshold when kktp_minimal is null', function () {
    ['siswa' => $siswa, 'mapel' => $mapel, 'semester' => $semester, 'asesmen' => $asesmen] = siapkanSiswaMapelSemester();
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'deskripsi' => 'TP tanpa ambang eksplisit', 'kktp_minimal' => null]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);

    $hasil = (new CapaianKompetensiGenerator())->generateNarasi($siswa, $mapel, $semester);

    expect($hasil['tertinggi'])->toBe('Menunjukkan penguasaan sangat baik dalam TP tanpa ambang eksplisit.');
});
```

- [ ] **Step 2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php
```

Expected: FAIL, `Class "App\Domains\Akademik\Services\CapaianKompetensiGenerator" not found`.

- [ ] **Step 3: Buat `CapaianKompetensiGenerator`**

`app/Domains/Akademik/Services/CapaianKompetensiGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Services;

use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;

final class CapaianKompetensiGenerator
{
    private const DEFAULT_AMBANG_KKTP = 75;

    /**
     * @return array{tertinggi: ?string, terendah: ?string}
     */
    public function generateNarasi(Siswa $siswa, MataPelajaran $mapel, Semester $semester): array
    {
        $komponenList = KomponenPenilaian::where('mata_pelajaran_id', $mapel->id)
            ->where('semester_id', $semester->id)
            ->get();

        if ($komponenList->isEmpty()) {
            return ['tertinggi' => null, 'terendah' => null];
        }

        $asesmenIds = Asesmen::where('mata_pelajaran_id', $mapel->id)
            ->where('semester_id', $semester->id)
            ->pluck('id');

        $skorPerKomponen = [];
        foreach ($komponenList as $komponen) {
            $rataRata = NilaiSiswa::where('siswa_id', $siswa->id)
                ->where('komponen_penilaian_id', $komponen->id)
                ->whereIn('asesmen_id', $asesmenIds)
                ->whereNotNull('nilai_angka')
                ->avg('nilai_angka');

            if ($rataRata !== null) {
                $skorPerKomponen[] = ['skor' => (float) $rataRata, 'komponen' => $komponen];
            }
        }

        if (empty($skorPerKomponen)) {
            return ['tertinggi' => null, 'terendah' => null];
        }

        $terurutTertinggi = collect($skorPerKomponen)->sortByDesc('skor')->first();
        $terurutTerendah = collect($skorPerKomponen)->sortBy('skor')->first();

        $narasiTertinggi = null;
        $ambangTertinggi = $terurutTertinggi['komponen']->kktp_minimal ?? self::DEFAULT_AMBANG_KKTP;
        if ($terurutTertinggi['skor'] >= $ambangTertinggi) {
            $narasiTertinggi = "Menunjukkan penguasaan sangat baik dalam {$terurutTertinggi['komponen']->deskripsi}.";
        }

        $narasiTerendah = null;
        $ambangTerendah = $terurutTerendah['komponen']->kktp_minimal ?? self::DEFAULT_AMBANG_KKTP;
        if ($terurutTerendah['skor'] < $ambangTerendah) {
            $narasiTerendah = "Perlu bimbingan dan pendampingan dalam {$terurutTerendah['komponen']->deskripsi}.";
        }

        return ['tertinggi' => $narasiTertinggi, 'terendah' => $narasiTerendah];
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan PASS**

```bash
php artisan test tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php
```

Expected: 5 test PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Services/CapaianKompetensiGenerator.php tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php
git commit -m "feat(akademik): tambah CapaianKompetensiGenerator (narasi TP otomatis)"
```

---

### Task 4: Workflow Definition, `CatatanWaliKelasData`, `SimpanCatatanWaliKelasAction`, `SubmitPengajuanRaporAction`

**Files:**
- Modify: `database/seeders/WorkflowDefinitionSeeder.php`
- Create: `app/Domains/Akademik/DataTransferObjects/CatatanWaliKelasData.php`
- Create: `app/Domains/Akademik/Actions/Rapor/SimpanCatatanWaliKelasAction.php`
- Create: `app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php`
- Test: `tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php`
- Test (regression): `tests/Unit/Domains/Workflow/WorkflowEngineTest.php` (existing, TIDAK diubah — harus tetap hijau)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\{PengajuanRapor,CatatanWaliKelas}` (Task 1), `App\Domains\Akademik\Enums\StatusPengajuanRapor` (Task 1), `App\Domains\Workflow\Actions\InitializeApprovalRequestAction`, `App\Domains\Workflow\Enums\ApprovalStatus` (existing, engine).
- Produces: `SimpanCatatanWaliKelasAction::execute(CatatanWaliKelasData $data): CatatanWaliKelas`, `SubmitPengajuanRaporAction::execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor` — dipakai Task 6 (test integrasi penuh).

- [ ] **Step 1: Cek isi `WorkflowDefinitionSeeder.php` saat ini**

```bash
cat database/seeders/WorkflowDefinitionSeeder.php
```

Pastikan isinya PERSIS seperti ini sebelum diedit (definisi `PENGADAAN_SARPRAS` dengan 2 step) — kalau berbeda, LAPORKAN sebelum melanjutkan:

```php
<?php

namespace Database\Seeders;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Workflow Pengadaan Sarpras Sekolah
        $pengadaan = WorkflowDefinition::updateOrCreate(
            ['code' => 'PENGADAAN_SARPRAS'],
            [
                'nama_workflow' => 'Pengadaan Sarana & Prasarana',
                'deskripsi' => 'Alur persetujuan usulan belanja & inventaris dari unit lembaga ke yayasan.',
                'is_active' => true,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Internal Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan & Pencairan Yayasan',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'bendahara_yayasan',
                'scope_level' => 'yayasan',
                'is_final_step' => true,
            ]
        );
    }
}
```

- [ ] **Step 2: Tambah definisi `RAPOR_SEMESTER`**

Ganti seluruh isi `database/seeders/WorkflowDefinitionSeeder.php` menjadi (menambahkan blok baru di akhir `run()`, TIDAK mengubah blok `PENGADAAN_SARPRAS` yang sudah ada):

```php
<?php

namespace Database\Seeders;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Workflow Pengadaan Sarpras Sekolah
        $pengadaan = WorkflowDefinition::updateOrCreate(
            ['code' => 'PENGADAAN_SARPRAS'],
            [
                'nama_workflow' => 'Pengadaan Sarana & Prasarana',
                'deskripsi' => 'Alur persetujuan usulan belanja & inventaris dari unit lembaga ke yayasan.',
                'is_active' => true,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Internal Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $pengadaan->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan & Pencairan Yayasan',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'bendahara_yayasan',
                'scope_level' => 'yayasan',
                'is_final_step' => true,
            ]
        );

        // 2. Workflow Persetujuan Rapor Semester
        $rapor = WorkflowDefinition::updateOrCreate(
            ['code' => 'RAPOR_SEMESTER'],
            [
                'nama_workflow' => 'Persetujuan Rapor Semester',
                'deskripsi' => 'Alur verifikasi Waka Kurikulum dan persetujuan akhir Kepala Sekolah untuk pengajuan rapor per kelas per semester.',
                'is_active' => true,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 1],
            [
                'step_name' => 'Verifikasi Waka Kurikulum',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'admin_akademik',
                'scope_level' => 'lembaga',
                'is_final_step' => false,
            ]
        );

        WorkflowStep::updateOrCreate(
            ['workflow_definition_id' => $rapor->id, 'step_number' => 2],
            [
                'step_name' => 'Persetujuan Akhir Kepala Sekolah',
                'approver_type' => ApproverType::Role,
                'approver_value' => 'kepala_sekolah',
                'scope_level' => 'lembaga',
                'is_final_step' => true,
            ]
        );
    }
}
```

- [ ] **Step 3: Jalankan seeder, verifikasi definisi lama tidak rusak**

```bash
php artisan db:seed --class=WorkflowDefinitionSeeder
php artisan test tests/Unit/Domains/Workflow/
```

Expected: seeder jalan tanpa error, test `WorkflowEngineTest` (test existing yang TIDAK disentuh plan ini) tetap PASS.

- [ ] **Step 4: Buat DTO `CatatanWaliKelasData`**

`app/Domains/Akademik/DataTransferObjects/CatatanWaliKelasData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\DataTransferObjects;

final readonly class CatatanWaliKelasData
{
    /**
     * @param  array<int, array<string, mixed>>  $ekstrakurikuler
     * @param  array<int, array<string, mixed>>  $prestasi
     * @param  array<int, array<string, mixed>>  $pklInfo
     */
    public function __construct(
        public int $siswaId,
        public int $semesterId,
        public ?string $catatanSikap,
        public ?string $catatanPerkembangan,
        public ?float $tinggiBadanCm,
        public ?float $beratBadanKg,
        public ?float $lingkarKepalaCm,
        public array $ekstrakurikuler,
        public array $prestasi,
        public array $pklInfo,
        public ?string $keteranganKenaikan,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            siswaId: (int) $data['siswa_id'],
            semesterId: (int) $data['semester_id'],
            catatanSikap: $data['catatan_sikap'] ?? null,
            catatanPerkembangan: $data['catatan_perkembangan'] ?? null,
            tinggiBadanCm: isset($data['tinggi_badan_cm']) ? (float) $data['tinggi_badan_cm'] : null,
            beratBadanKg: isset($data['berat_badan_kg']) ? (float) $data['berat_badan_kg'] : null,
            lingkarKepalaCm: isset($data['lingkar_kepala_cm']) ? (float) $data['lingkar_kepala_cm'] : null,
            ekstrakurikuler: $data['ekstrakurikuler'] ?? [],
            prestasi: $data['prestasi'] ?? [],
            pklInfo: $data['pkl_info'] ?? [],
            keteranganKenaikan: $data['keterangan_kenaikan'] ?? null,
        );
    }
}
```

- [ ] **Step 5: Buat `SimpanCatatanWaliKelasAction`**

`app/Domains/Akademik/Actions/Rapor/SimpanCatatanWaliKelasAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\CatatanWaliKelas;

final class SimpanCatatanWaliKelasAction
{
    public function execute(CatatanWaliKelasData $data): CatatanWaliKelas
    {
        return CatatanWaliKelas::updateOrCreate(
            ['siswa_id' => $data->siswaId, 'semester_id' => $data->semesterId],
            [
                'catatan_sikap' => $data->catatanSikap,
                'catatan_perkembangan' => $data->catatanPerkembangan,
                'tinggi_badan_cm' => $data->tinggiBadanCm,
                'berat_badan_kg' => $data->beratBadanKg,
                'lingkar_kepala_cm' => $data->lingkarKepalaCm,
                'ekstrakurikuler' => $data->ekstrakurikuler,
                'prestasi' => $data->prestasi,
                'pkl_info' => $data->pklInfo,
                'keterangan_kenaikan' => $data->keteranganKenaikan,
            ]
        );
    }
}
```

- [ ] **Step 6: Buat `SubmitPengajuanRaporAction`**

`app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\CatatanWaliKelas;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitPengajuanRaporAction
{
    public function __construct(
        private readonly InitializeApprovalRequestAction $initializeApprovalRequestAction,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(Kelas $kelas, Semester $semester, User $user): PengajuanRapor
    {
        $siswaList = $kelas->siswa()->get();
        $siswaIdsWithCatatan = CatatanWaliKelas::where('semester_id', $semester->id)
            ->whereIn('siswa_id', $siswaList->pluck('id'))
            ->pluck('siswa_id');

        $siswaBelumLengkap = $siswaList->whereNotIn('id', $siswaIdsWithCatatan);

        if ($siswaBelumLengkap->isNotEmpty()) {
            $daftarNama = $siswaBelumLengkap->pluck('nama_lengkap')->implode(', ');
            throw ValidationException::withMessages([
                'catatan_wali_kelas' => "Siswa berikut belum memiliki catatan wali kelas: {$daftarNama}.",
            ]);
        }

        return DB::transaction(function () use ($kelas, $semester, $user) {
            $pengajuanRapor = PengajuanRapor::updateOrCreate(
                ['kelas_id' => $kelas->id, 'semester_id' => $semester->id],
                ['status' => StatusPengajuanRapor::Diajukan, 'diajukan_oleh' => $user->id, 'diajukan_pada' => now()]
            );

            $existingApprovalRequest = $pengajuanRapor->approvalRequest;

            if ($existingApprovalRequest) {
                $firstStep = $existingApprovalRequest->workflowDefinition?->firstStep();
                $existingApprovalRequest->current_step_id = $firstStep?->id;
                $existingApprovalRequest->status = ApprovalStatus::Pending;
                $existingApprovalRequest->last_notes = null;
                $existingApprovalRequest->save();
            } else {
                $this->initializeApprovalRequestAction->execute('RAPOR_SEMESTER', $pengajuanRapor, $user);
            }

            return $pengajuanRapor->fresh();
        });
    }
}
```

- [ ] **Step 7: Tulis test**

`tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;

function siapkanKelasDenganSiswa(int $jumlahSiswa = 2): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswaList = Siswa::factory()->count($jumlahSiswa)->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    return compact('lembaga', 'semester', 'kelas', 'siswaList', 'user');
}

it('rejects submission when not every siswa in the kelas has a CatatanWaliKelas', function () {
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(2);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
        'siswa_id' => $siswaList[0]->id,
        'semester_id' => $semester->id,
    ]));
    // siswaList[1] sengaja tidak dikasih catatan

    expect(fn () => (new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $user))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('creates a PengajuanRapor and initializes an ApprovalRequest when every siswa has a CatatanWaliKelas', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(2);

    foreach ($siswaList as $siswa) {
        (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray([
            'siswa_id' => $siswa->id,
            'semester_id' => $semester->id,
        ]));
    }

    $pengajuan = (new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $user);

    expect($pengajuan->status)->toBe(StatusPengajuanRapor::Diajukan);
    expect($pengajuan->diajukan_oleh)->toBe($user->id);
    expect($pengajuan->approvalRequest)->not->toBeNull();
    expect($pengajuan->approvalRequest->status)->toBe(ApprovalStatus::Pending);
});

it('resets the same ApprovalRequest to its first step on resubmission after rejection, instead of creating a new one', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'siswaList' => $siswaList, 'user' => $user] = siapkanKelasDenganSiswa(1);
    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswaList[0]->id, 'semester_id' => $semester->id]));

    $action = new SubmitPengajuanRaporAction(app(\App\Domains\Workflow\Actions\InitializeApprovalRequestAction::class));
    $pengajuan = $action->execute($kelas, $semester, $user);
    $approvalRequestIdPertama = $pengajuan->approvalRequest->id;

    // Simulasikan penolakan langsung di level engine (tanpa lewat VerifyPengajuanRaporAction, karena itu Task 5)
    $pengajuan->approvalRequest->update(['status' => ApprovalStatus::Rejected]);
    $pengajuan->update(['status' => StatusPengajuanRapor::Ditolak]);

    $pengajuanResubmit = $action->execute($kelas, $semester, $user);

    expect($pengajuanResubmit->id)->toBe($pengajuan->id);
    expect($pengajuanResubmit->approvalRequest->id)->toBe($approvalRequestIdPertama);
    expect($pengajuanResubmit->approvalRequest->status)->toBe(ApprovalStatus::Pending);
    expect($pengajuanResubmit->status)->toBe(StatusPengajuanRapor::Diajukan);
});
```

- [ ] **Step 8: Jalankan test**

```bash
php artisan test tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php
```

Expected: 3 test PASS.

- [ ] **Step 9: Commit**

```bash
git add database/seeders/WorkflowDefinitionSeeder.php app/Domains/Akademik/DataTransferObjects/CatatanWaliKelasData.php app/Domains/Akademik/Actions/Rapor/SimpanCatatanWaliKelasAction.php app/Domains/Akademik/Actions/Rapor/SubmitPengajuanRaporAction.php tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php
git commit -m "feat(akademik): definisi workflow RAPOR_SEMESTER, SimpanCatatanWaliKelasAction, SubmitPengajuanRaporAction"
```

---

### Task 5: `VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`, Penegakan Kunci Nilai

**Files:**
- Create: `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`
- Create: `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`
- Modify: `app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php`
- Test: `tests/Feature/Akademik/RaporApprovalActionsTest.php`
- Test (regression): `tests/Feature/Guru/AsesmenControllerTest.php` (existing dari 04a, TIDAK diubah — harus tetap hijau, membuktikan guard baru tidak memblokir kasus normal)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Models\PengajuanRapor` (Task 1), `App\Domains\Workflow\Actions\ProcessApprovalAction`, `App\Domains\Workflow\Enums\{ApprovalAction,ApprovalStatus}` (existing, engine).
- Produces: `VerifyPengajuanRaporAction::execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan): PengajuanRapor`, `ApprovePengajuanRaporAction::execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan): PengajuanRapor` — dipakai Task 6.

- [ ] **Step 1: Buat `VerifyPengajuanRaporAction`**

`app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VerifyPengajuanRaporAction
{
    public function __construct(
        private readonly ProcessApprovalAction $processApprovalAction,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $approvalRequest = $pengajuanRapor->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan rapor ini belum pernah diajukan.',
            ]);
        }

        return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
            $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Rejected) {
                $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
                $pengajuanRapor->catatan_revisi = $catatan;
            } elseif ($approvalRequest->status === ApprovalStatus::InReview) {
                $pengajuanRapor->status = StatusPengajuanRapor::Diverifikasi;
                $pengajuanRapor->diverifikasi_oleh = $user->id;
                $pengajuanRapor->diverifikasi_pada = now();
            }

            $pengajuanRapor->save();

            return $pengajuanRapor->fresh();
        });
    }
}
```

- [ ] **Step 2: Buat `ApprovePengajuanRaporAction`**

`app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rapor;

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePengajuanRaporAction
{
    public function __construct(
        private readonly ProcessApprovalAction $processApprovalAction,
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $approvalRequest = $pengajuanRapor->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan rapor ini belum pernah diajukan.',
            ]);
        }

        return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
            $this->processApprovalAction->execute($approvalRequest, $user, $action, $catatan);
            $approvalRequest->refresh();

            if ($approvalRequest->status === ApprovalStatus::Rejected) {
                $pengajuanRapor->status = StatusPengajuanRapor::Ditolak;
                $pengajuanRapor->catatan_revisi = $catatan;
            } elseif ($approvalRequest->status === ApprovalStatus::Approved) {
                $pengajuanRapor->status = StatusPengajuanRapor::Disetujui;
                $pengajuanRapor->disetujui_oleh = $user->id;
                $pengajuanRapor->disetujui_pada = now();
            }

            $pengajuanRapor->save();

            return $pengajuanRapor->fresh();
        });
    }
}
```

- [ ] **Step 3: Tambah penegakan kunci nilai di `SimpanNilaiSiswaAction` (04a)**

Baca dulu isi file `app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php` saat ini — HARUS persis:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use Illuminate\Support\Facades\DB;

final class SimpanNilaiSiswaAction
{
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $komponenIds, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    if (! $komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });
    }
}
```

Kalau berbeda dari di atas, LAPORKAN sebelum melanjutkan. Kalau sama, ganti SELURUH isi file jadi:

```php
<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Penilaian;

use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Models\PengajuanRapor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SimpanNilaiSiswaAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Asesmen $asesmen, NilaiSiswaBatchData $data): void
    {
        $terkunci = PengajuanRapor::where('kelas_id', $asesmen->kelas_id)
            ->where('semester_id', $asesmen->semester_id)
            ->where('status', StatusPengajuanRapor::Disetujui)
            ->exists();

        if ($terkunci) {
            throw ValidationException::withMessages([
                'nilai' => 'Nilai untuk kelas dan semester ini sudah dikunci karena rapor sudah disetujui.',
            ]);
        }

        $komponenIds = $asesmen->komponenPenilaian()->pluck('komponen_penilaian.id');
        $siswaIds = $asesmen->kelas->siswa()->pluck('id');

        DB::transaction(function () use ($asesmen, $data, $komponenIds, $siswaIds) {
            foreach ($data->nilai as $siswaId => $perKomponen) {
                if (! $siswaIds->contains((int) $siswaId)) {
                    continue;
                }

                foreach ($perKomponen as $komponenId => $values) {
                    if (! $komponenIds->contains((int) $komponenId)) {
                        continue;
                    }

                    NilaiSiswa::updateOrCreate(
                        ['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaId, 'komponen_penilaian_id' => $komponenId],
                        [
                            'nilai_angka' => isset($values['nilai_angka']) && $values['nilai_angka'] !== '' ? (int) $values['nilai_angka'] : null,
                            'catatan' => $values['catatan'] ?? null,
                        ]
                    );
                }
            }
        });
    }
}
```

- [ ] **Step 4: Tulis test**

`tests/Feature/Akademik/RaporApprovalActionsTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Penilaian\SimpanNilaiSiswaAction;
use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\DataTransferObjects\NilaiSiswaBatchData;
use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

function siapkanPengajuanDiajukan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userWaka = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userWaka->assignRole($roleWaka);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));

    $submitAction = new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class));
    $pengajuan = $submitAction->execute($kelas, $semester, $userWali);

    return compact('lembaga', 'semester', 'kelas', 'siswa', 'mapel', 'pengajuan', 'userWaka', 'userKepsek', 'userWali');
}

it('completes the full happy path: submit -> verify -> approve, locking nilai afterward', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'mapel' => $mapel, 'pengajuan' => $pengajuan, 'userWaka' => $userWaka, 'userKepsek' => $userKepsek] = siapkanPengajuanDiajukan();

    $verifyAction = new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class));
    $diverifikasi = $verifyAction->execute($pengajuan, $userWaka, ApprovalAction::Approve, 'Lengkap');
    expect($diverifikasi->status)->toBe(StatusPengajuanRapor::Diverifikasi);
    expect($diverifikasi->diverifikasi_oleh)->toBe($userWaka->id);

    $approveAction = new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class));
    $disetujui = $approveAction->execute($diverifikasi, $userKepsek, ApprovalAction::Approve, 'Setuju');
    expect($disetujui->status)->toBe(StatusPengajuanRapor::Disetujui);
    expect($disetujui->disetujui_oleh)->toBe($userKepsek->id);

    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);

    expect(fn () => (new SimpanNilaiSiswaAction())->execute($asesmen, NilaiSiswaBatchData::fromArray(['nilai' => []])))
        ->toThrow(ValidationException::class);
});

it('does not lock nilai for a different kelas or semester', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan, 'lembaga' => $lembaga] = siapkanPengajuanDiajukan();

    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);
    (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve);

    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmenLain = Asesmen::factory()->create(['kelas_id' => $kelasLain->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semesterLain->id]);

    (new SimpanNilaiSiswaAction())->execute($asesmenLain, NilaiSiswaBatchData::fromArray(['nilai' => []]));
    expect(true)->toBeTrue(); // tidak throw = lulus
});

it('rejects at the verify stage and records catatan_revisi, allowing resubmission afterward', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['semester' => $semester, 'kelas' => $kelas, 'pengajuan' => $pengajuan, 'userWaka' => $userWaka, 'userWali' => $userWali] = siapkanPengajuanDiajukan();

    $verifyAction = new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class));
    $ditolak = $verifyAction->execute($pengajuan, $userWaka, ApprovalAction::Reject, 'Nilai belum lengkap');

    expect($ditolak->status)->toBe(StatusPengajuanRapor::Ditolak);
    expect($ditolak->catatan_revisi)->toBe('Nilai belum lengkap');

    $submitAction = new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class));
    $diajukanUlang = $submitAction->execute($kelas, $semester, $userWali);

    expect($diajukanUlang->status)->toBe(StatusPengajuanRapor::Diajukan);
    expect($diajukanUlang->id)->toBe($pengajuan->id);
});
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/Akademik/RaporApprovalActionsTest.php
```

Expected: 3 test PASS.

- [ ] **Step 6: Jalankan regression 04a**

```bash
php artisan test tests/Feature/Guru/AsesmenControllerTest.php
```

Expected: semua PASS tanpa perubahan assertion — membuktikan guard baru tidak memblokir simpan-nilai normal (tanpa `PengajuanRapor` sama sekali).

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php app/Domains/Akademik/Actions/Penilaian/SimpanNilaiSiswaAction.php tests/Feature/Akademik/RaporApprovalActionsTest.php
git commit -m "feat(akademik): VerifyPengajuanRaporAction, ApprovePengajuanRaporAction, penegakan kunci nilai"
```

---

### Task 6: Permission Baru & Test Integrasi Cross-Tenant

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`
- Modify: `database/seeders/RoleSeeder.php`
- Test: `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php`

**Interfaces:**
- Consumes: seluruh Action dari Task 4-5.
- Produces: tidak ada interface baru — task ini murni permission + test keamanan penutup.

- [ ] **Step 1: Tambah 4 permission baru**

Di `database/seeders/PermissionSeeder.php`, cari baris (sekitar baris 53):
```php
            'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.view',
```
Ganti jadi:
```php
            'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.view',
            'rapor.input-wali', 'rapor.ajukan', 'rapor.verify', 'rapor.approve',
```

- [ ] **Step 2: Assign permission ke role**

Di `database/seeders/RoleSeeder.php`:

Cari blok `if ($name === 'kepala_sekolah') { $role->givePermissionTo([...]); }` (sekitar baris 59-68). Isi array-nya saat ini:
```php
                $role->givePermissionTo([
                    'spmb-pendaftaran.view', 'spmb-pendaftaran.verifikasi-dokumen', 'spmb-pendaftaran.nilai-seleksi',
                    'spmb-pendaftaran.tetapkan-keputusan', 'spmb-pendaftaran.terbitkan-sk',
                    'tagihan.view',
                    'komponen-penilaian.kelola', 'rapor.view',
                    'kenaikan-kelas.kelola',
                    'rpp.view', 'rpp.verify',
                ]);
```
Ganti baris `'komponen-penilaian.kelola', 'rapor.view',` jadi `'komponen-penilaian.kelola', 'rapor.view', 'rapor.approve',`.

Cari blok `if ($name === 'guru') { $role->givePermissionTo([...]); }` (sekitar baris 70-76). Isi array-nya saat ini:
```php
                $role->givePermissionTo([
                    'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri',
                    'kasus.ajukan', 'kasus.view',
                    'rpp.view', 'rpp.kelola',
                ]);
```
Ganti baris `'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri',` jadi `'presensi.isi', 'asesmen.kelola', 'komponen-penilaian.kelola-sendiri', 'rapor.input-wali', 'rapor.ajukan',`.

Cari blok `if ($name === 'admin_akademik') { $role->givePermissionTo([...]); }` (sekitar baris 78-99). Cari baris `'rapor.view',` di dalam array itu (BUKAN di blok `kepala_sekolah` yang sudah diedit di atas — ada juga `'rapor.view',` di blok `admin_akademik`). Ganti jadi `'rapor.view', 'rapor.verify',`.

- [ ] **Step 3: Jalankan seeder permission, verifikasi tidak error**

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

Expected: kedua seeder jalan tanpa error.

- [ ] **Step 4: Tulis test integrasi cross-tenant (bukti `ApprovalRequest` milik lembaga lain tidak bisa diproses)**

`tests/Feature/Akademik/RaporApprovalTenantScopeTest.php`:

```php
<?php

use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

it('never resolves a PengajuanRapor belonging to another lembaga by id, so its ApprovalRequest is unreachable cross-tenant', function () {
    $this->seed(WorkflowDefinitionSeeder::class);

    $yayasan = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaranLain = TahunAjaran::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $semesterLain = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaranLain->id]);
    $kelasLain = Kelas::factory()->create(['lembaga_id' => $lembagaLain->id, 'tahun_ajaran_id' => $tahunAjaranLain->id]);
    $siswaLain = Siswa::factory()->create(['lembaga_id' => $lembagaLain->id, 'kelas_id' => $kelasLain->id]);
    $userWaliLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswaLain->id, 'semester_id' => $semesterLain->id]));
    $pengajuanLain = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelasLain, $semesterLain, $userWaliLain);

    $lembagaSaya = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $userWakaSaya = User::factory()->create(['lembaga_id' => $lembagaSaya->id]);
    $userWakaSaya->assignRole($roleWaka);
    $this->actingAs($userWakaSaya);

    // Route-model-binding style lookup (tenant-scoped via BelongsToTenant): PengajuanRapor milik
    // lembaga lain tidak boleh bisa di-resolve oleh aktor lembaga sendiri.
    expect(PengajuanRapor::find($pengajuanLain->id))->toBeNull();
});

it('rejects verify/approve when the acting user role does not match the current workflow step approver, even with a valid ApprovalRequest', function () {
    $this->seed(WorkflowDefinitionSeeder::class);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    // userKepsek belum punya giliran (step 1 = admin_akademik) - coba approve langsung harus ditolak resolver engine.
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKepsek->assignRole($roleKepsek);

    expect(fn () => (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userKepsek, ApprovalAction::Approve))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/Akademik/RaporApprovalTenantScopeTest.php
```

Expected: 2 test PASS.

- [ ] **Step 6: Jalankan seluruh test scoped Task 1-6 sebagai regresi gabungan**

```bash
php artisan test tests/Unit/Models/PengajuanRaporTest.php tests/Unit/Models/CatatanWaliKelasTest.php tests/Feature/Admin/KomponenPenilaianCrudTest.php tests/Feature/Guru/KomponenPenilaianControllerTest.php tests/Feature/Akademik/CapaianKompetensiGeneratorTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Guru/AsesmenControllerTest.php tests/Unit/Domains/Workflow/
```

Expected: semua PASS.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/PermissionSeeder.php database/seeders/RoleSeeder.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php
git commit -m "feat(akademik): permission rapor.input-wali/ajukan/verify/approve + test cross-tenant"
```

---

### Task 7: Verifikasi Akhir & Handoff

**Files:**
- Create: `.agents/logs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`
- Modify: `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md` (update baris status 04b di tabel navigasi jadi SELESAI)

**Interfaces:**
- Consumes: hasil seluruh Task 1-6.
- Produces: tidak ada — task penutup.

- [ ] **Step 1: Tanyakan ke user apakah mau menjalankan full test suite**

Sebelum menjalankan `php artisan test` tanpa filter, tanyakan dulu ke user — hanya jalankan kalau user menyetujui. (Kalau plan ini dijalankan tanpa ada user yang bisa ditanya real-time — mis. lewat agent otomatis non-interaktif — catat di handoff log bahwa full suite BELUM dijalankan dan minta user menjalankannya sendiri, jangan asumsikan izin.)

- [ ] **Step 2: (Jika disetujui) Jalankan full suite**

```bash
php artisan test
```

Expected: 0 failed. Kalau ada failure yang TIDAK terkait file yang disentuh plan ini, re-run test yang gagal secara terisolasi 2-3x untuk pastikan itu flaky pre-existing, bukan regresi baru, sebelum menyimpulkan aman.

- [ ] **Step 3: Update tabel navigasi master plan**

Di `.agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md`, cari baris:
```
| **04b** | **Adaptive E-Rapor Engine (narasi TP, approval workflow, PDF berjenjang)** | `.agents/specs/akademik-04b-e-rapor.md` | `.agents/plans/akademik-04b-e-rapor.md` | `.agents/logs/akademik-04b-e-rapor.md` | ⚪ PENDING |
```
Ganti jadi:
```
| **04b** | **Adaptive E-Rapor Engine: Backend & Approval Workflow** | [`.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/specs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md) | [`.agents/plans/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/plans/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md) | [`.agents/logs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md`](file:///d:/laragon/www/pintera-app/.agents/logs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md) | 🟢 **SELESAI (COMPLETED)** |
| **04c** | **UI 4 Role (Guru/Wali Kelas/Waka/Kepsek)** | `.agents/specs/akademik-04c-rapor-ui.md` | `.agents/plans/akademik-04c-rapor-ui.md` | `.agents/logs/akademik-04c-rapor-ui.md` | ⚪ PENDING |
| **04d** | **4 Template PDF Berjenjang** | `.agents/specs/akademik-04d-rapor-pdf.md` | `.agents/plans/akademik-04d-rapor-pdf.md` | `.agents/logs/akademik-04d-rapor-pdf.md` | ⚪ PENDING |
```

Juga hapus catatan blok kutipan (`> **Catatan untuk sesi berikutnya:** ...`) yang menyebut gap `TenantScope.php` KALAU catatan itu sudah tidak relevan lagi (baca dulu apakah masih ada di file — kalau masih ada dan belum ditangani, JANGAN dihapus, biarkan tetap ada untuk sub-task berikutnya).

- [ ] **Step 4: Tulis handoff log**

`.agents/logs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md` — isi dengan ringkasan: apa yang dikerjakan per task, hasil test tiap task, commit hash tiap task, dan status akhir. Format bebas mengikuti gaya `.agents/logs/2026-08-19-1015-akademik-04a-migrasi-komponen-penilaian-rapor.md` sebagai referensi struktur (baca file itu dulu untuk contoh).

- [ ] **Step 5: Commit**

```bash
git add .agents/plans/2026-08-17-1015-penyempurnaan-modul-akademik.md .agents/logs/2026-08-19-1530-akademik-04b-rapor-workflow-backend.md
git commit -m "docs(akademik): tutup Sub-Task 04b, update master plan & handoff log"
```
