# Master Sarpras, Ruangan, Kategori, dan Aset Inventaris Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul Master Sarana & Prasarana (Gedung, Ruangan, Kategori Aset, Aset/Barang Hybrid, Mutasi Lokasi, dan Kartu Inventaris Ruangan PDF) menggunakan arsitektur Domain `app/Domains/Sarpras/` yang terintegrasi dengan Jadwal Pelajaran (Anti-Bentrok Ruangan) dan didukung otorisasi multi-scope.

**Architecture:** Domain-Driven Design (DDD / Modular Monolith) sesuai `laravel-feature-standard`, menggunakan `TenantContext` dari `Domains/Shared/`, Action class untuk setiap mutasi/logika bisnis, strongly-typed DTOs, ViewModel untuk agregasi data view, Thin Controller di `app/Http/Controllers/Lembaga/Sarpras/` dan `app/Http/Controllers/Yayasan/Sarpras/`, serta Blade Components + Alpine.js untuk antarmuka pengguna.

**Tech Stack:** Laravel 12, PHP 8.2+, MySQL, Tailwind CSS, Alpine.js, Spatie Permission, Barryvdh DomPDF.

## Global Constraints
- Namespace Domain: `App\Domains\Sarpras\...` dan `App\Domains\Shared\...`.
- Multi-Tenant Isolation: Menggunakan `TenantContext` untuk mengunci scope data per lembaga dan mendukung `is_shared` untuk fasilitas bersama yayasan.
- Format Permission: Standard dot-notation `[domain].[entity].[action]` (misal: `sarpras.gedung.view`, `sarpras.aset.manage`).
- TDD / Test Coverage: Setiap Action dan Controller wajib memiliki automated test (Pest/PHPUnit).

---

### Task 1: Fondasi Shared Context, Migrations, dan Enums Sarpras

**Files:**
- Create: `app/Domains/Shared/Context/Contracts/TenantContextInterface.php`
- Create: `app/Domains/Shared/Context/TenantContext.php`
- Create: `app/Domains/Sarpras/Enums/JenisRuangan.php`
- Create: `app/Domains/Sarpras/Enums/KondisiAset.php`
- Create: `app/Domains/Sarpras/Enums/TipePencatatanAset.php`
- Create: `app/Domains/Sarpras/Enums/SumberPerolehanAset.php`
- Create: `database/migrations/2026_08_16_120000_create_gedung_table.php`
- Create: `database/migrations/2026_08_16_120100_create_ruangan_table.php`
- Create: `database/migrations/2026_08_16_120200_add_ruangan_id_to_kelas_and_jadwal_tables.php`
- Create: `database/migrations/2026_08_16_120300_create_kategori_aset_table.php`
- Create: `database/migrations/2026_08_16_120400_create_aset_barang_table.php`
- Create: `database/migrations/2026_08_16_120500_create_riwayat_mutasi_aset_table.php`
- Test: `tests/Unit/Domains/Shared/TenantContextTest.php`

**Interfaces:**
- Produces:
  - `TenantContext::activeLembagaId(): ?int`
  - `TenantContext::activeYayasanId(): ?int`
  - `TenantContext::isYayasanScope(): bool`

- [x] **Step 1: Write failing test for TenantContext**
```php
namespace Tests\Unit\Domains\Shared;

use App\Domains\Shared\Context\TenantContext;
use App\Models\Lembaga;
use App\Models\User;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    public function test_resolves_active_lembaga_for_regular_user(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $this->actingAs($user);
        $context = new TenantContext();

        $this->assertEquals($lembaga->id, $context->activeLembagaId());
        $this->assertFalse($context->isYayasanScope());
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Shared/TenantContextTest.php`  
Expected: FAIL with class not found.

- [x] **Step 3: Implement TenantContext, Enums, and Migrations**
Implement `TenantContext`, `JenisRuangan`, `KondisiAset`, `TipePencatatanAset`, `SumberPerolehanAset`, and run `php artisan migrate`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Shared/TenantContextTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Shared/ app/Domains/Sarpras/Enums/ database/migrations/ tests/Unit/Domains/Shared/
git commit -m "feat(sarpras): add shared tenant context, sarpras enums, and database migrations"
```

---

### Task 2: Eloquent Models, Relasi, & RBAC Seeders

**Files:**
- Create: `app/Domains/Sarpras/Models/Gedung.php`
- Create: `app/Domains/Sarpras/Models/Ruangan.php`
- Create: `app/Domains/Sarpras/Models/KategoriAset.php`
- Create: `app/Domains/Sarpras/Models/AsetBarang.php`
- Create: `app/Domains/Sarpras/Models/RiwayatMutasiAset.php`
- Modify: `app/Models/Kelas.php:20-40` (tambahkan relasi `ruangan()`)
- Modify: `app/Models/JadwalPelajaran.php:20-40` (tambahkan relasi `ruangan()`)
- Create: `database/seeders/SarprasPermissionSeeder.php`
- Test: `tests/Unit/Domains/Sarpras/SarprasModelsTest.php`

**Interfaces:**
- Produces:
  - `Gedung::ruangan()` (HasMany)
  - `Ruangan::gedung()` (BelongsTo), `Ruangan::aset()` (HasMany), `Ruangan::penanggungJawab()` (BelongsTo Guru)
  - `AsetBarang::ruangan()` (BelongsTo), `AsetBarang::kategori()` (BelongsTo), `AsetBarang::riwayatMutasi()` (HasMany)
  - `Kelas::ruangan()` (BelongsTo Home Room)
  - `JadwalPelajaran::ruangan()` (BelongsTo)

- [x] **Step 1: Write failing test for Sarpras models and relationships**
```php
namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Tests\TestCase;

class SarprasModelsTest extends TestCase
{
    public function test_gedung_and_ruangan_hierarchy(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-A',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 3,
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-101',
            'nama_ruangan' => 'Ruang 101 Teori',
            'lantai' => 1,
            'jenis_ruangan' => 'kelas_teori',
            'kapasitas_siswa' => 36,
        ]);

        $this->assertCount(1, $gedung->ruangan);
        $this->assertEquals('Gedung Utama', $ruangan->gedung->nama_gedung);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Sarpras/SarprasModelsTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Models and Seeder**
Write `Gedung`, `Ruangan`, `KategoriAset`, `AsetBarang`, `RiwayatMutasiAset`, update `Kelas` & `JadwalPelajaran`, and run `SarprasPermissionSeeder`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Sarpras/SarprasModelsTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Sarpras/Models/ app/Models/ database/seeders/ tests/Unit/Domains/Sarpras/
git commit -m "feat(sarpras): implement Sarpras Eloquent models, relations, and permissions"
```

---

### Task 3: DTO & Action Classes (Gedung, Ruangan, & Anti-Bentrok Jadwal)

**Files:**
- Create: `app/Domains/Sarpras/DataTransferObjects/GedungData.php`
- Create: `app/Domains/Sarpras/DataTransferObjects/RuanganData.php`
- Create: `app/Domains/Sarpras/Actions/CreateGedungAction.php`
- Create: `app/Domains/Sarpras/Actions/UpdateGedungAction.php`
- Create: `app/Domains/Sarpras/Actions/CreateRuanganAction.php`
- Create: `app/Domains/Sarpras/Actions/UpdateRuanganAction.php`
- Create: `app/Domains/Sarpras/Actions/ValidateRoomClashAction.php`
- Test: `tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php`

**Interfaces:**
- Consumes: `TenantContext`, `GedungData`, `RuanganData`.
- Produces:
  - `CreateGedungAction::execute(GedungData $data): Gedung`
  - `CreateRuanganAction::execute(RuanganData $data): Ruangan`
  - `ValidateRoomClashAction::execute(int $ruanganId, string $hari, int $jamPelajaranId, int $tahunAjaranId, ?int $ignoreJadwalId = null): bool`

- [x] **Step 1: Write failing test for Room Clash Validation and Create Actions**
```php
namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Sarpras\Actions\CreateRuanganAction;
use App\Domains\Sarpras\Actions\ValidateRoomClashAction;
use App\Domains\Sarpras\DataTransferObjects\RuanganData;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\JadwalPelajaran;
use Tests\TestCase;

class GedungRuanganActionTest extends TestCase
{
    public function test_detects_room_clash_in_jadwal_pelajaran(): void
    {
        $ruangan = Ruangan::factory()->create();
        $jadwal = JadwalPelajaran::factory()->create([
            'ruangan_id' => $ruangan->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => 1,
            'tahun_ajaran_id' => 1,
        ]);

        $action = new ValidateRoomClashAction();
        $isClash = $action->execute($ruangan->id, 'senin', 1, 1);
        $this->assertTrue($isClash);

        $isClashDiffDay = $action->execute($ruangan->id, 'selasa', 1, 1);
        $this->assertFalse($isClashDiffDay);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Actions & DTOs**
Implement `GedungData`, `RuanganData`, `CreateGedungAction`, `UpdateGedungAction`, `CreateRuanganAction`, `UpdateRuanganAction`, `ValidateRoomClashAction`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Sarpras/GedungRuanganActionTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Sarpras/Actions/ app/Domains/Sarpras/DataTransferObjects/ tests/Unit/Domains/Sarpras/
git commit -m "feat(sarpras): implement Gedung, Ruangan, and Room Clash validation actions"
```

---

### Task 4: DTO & Action Classes (Kategori, Aset Barang, & Mutasi Lokasi)

**Files:**
- Create: `app/Domains/Sarpras/DataTransferObjects/KategoriAsetData.php`
- Create: `app/Domains/Sarpras/DataTransferObjects/AsetBarangData.php`
- Create: `app/Domains/Sarpras/DataTransferObjects/MutasiAsetData.php`
- Create: `app/Domains/Sarpras/Actions/CreateKategoriAsetAction.php`
- Create: `app/Domains/Sarpras/Actions/CreateAsetBarangAction.php`
- Create: `app/Domains/Sarpras/Actions/UpdateAsetBarangAction.php`
- Create: `app/Domains/Sarpras/Actions/MutasiAsetRuanganAction.php`
- Test: `tests/Unit/Domains/Sarpras/AsetMutasiActionTest.php`

**Interfaces:**
- Consumes: `AsetBarangData`, `MutasiAsetData`.
- Produces:
  - `CreateAsetBarangAction::execute(AsetBarangData $data): AsetBarang`
  - `MutasiAsetRuanganAction::execute(MutasiAsetData $data): RiwayatMutasiAset`

- [x] **Step 1: Write failing test for Aset Creation & Location Mutation**
```php
namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Sarpras\Actions\MutasiAsetRuanganAction;
use App\Domains\Sarpras\DataTransferObjects\MutasiAsetData;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\User;
use Tests\TestCase;

class AsetMutasiActionTest extends TestCase
{
    public function test_mutasi_aset_moves_location_and_creates_log(): void
    {
        $ruangAsal = Ruangan::factory()->create();
        $ruangTujuan = Ruangan::factory()->create();
        $user = User::factory()->create();

        $aset = AsetBarang::factory()->create([
            'ruangan_id' => $ruangAsal->id,
            'qty' => 10,
            'tipe_pencatatan' => 'unit',
        ]);

        $dto = new MutasiAsetData(
            asetBarangId: $aset->id,
            ruanganTujuanId: $ruangTujuan->id,
            qtyPindah: 1,
            tanggalMutasi: now()->toDateString(),
            alasanMutasi: 'Kebutuhan KBM Lab Baru',
            dilakukanOlehUserId: $user->id
        );

        $action = new MutasiAsetRuanganAction();
        $log = $action->execute($dto);

        $this->assertEquals($ruangTujuan->id, $aset->fresh()->ruangan_id);
        $this->assertEquals($ruangAsal->id, $log->ruangan_asal_id);
        $this->assertEquals($ruangTujuan->id, $log->ruangan_tujuan_id);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Sarpras/AsetMutasiActionTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Aset & Mutasi Actions**
Implement `AsetBarangData`, `MutasiAsetData`, `CreateAsetBarangAction`, `UpdateAsetBarangAction`, `MutasiAsetRuanganAction` (mendukung transfer seluruh unit atau pemecahan batch qty).

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Sarpras/AsetMutasiActionTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Sarpras/Actions/ app/Domains/Sarpras/DataTransferObjects/ tests/Unit/Domains/Sarpras/
git commit -m "feat(sarpras): implement Aset and Room Mutation actions with audit logging"
```

---

### Task 5: HTTP Layer (FormRequests, Controllers Scope Lembaga & Yayasan, Routes)

**Files:**
- Create: `app/Http/Requests/Sarpras/StoreGedungRequest.php`
- Create: `app/Http/Requests/Sarpras/StoreRuanganRequest.php`
- Create: `app/Http/Requests/Sarpras/StoreAsetBarangRequest.php`
- Create: `app/Http/Requests/Sarpras/StoreMutasiAsetRequest.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/GedungController.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/RuanganController.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/KategoriAsetController.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/AsetBarangController.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/MutasiAsetController.php`
- Create: `app/Http/Controllers/Lembaga/Sarpras/KirController.php`
- Create: `app/Http/Controllers/Yayasan/Sarpras/RekapAsetGlobalController.php`
- Modify: `routes/admin.php:260-310` (daftarkan route Sarpras ber-prefix `sarpras/`)
- Test: `tests/Feature/Sarpras/GedungRuanganControllerTest.php`
- Test: `tests/Feature/Sarpras/AsetControllerTest.php`

- [x] **Step 1: Write failing Feature test for Controllers**
```php
namespace Tests\Feature\Sarpras;

use App\Domains\Sarpras\Models\Gedung;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\SarprasPermissionSeeder;
use Tests\TestCase;

class GedungRuanganControllerTest extends TestCase
{
    public function test_admin_sarpras_can_create_gedung(): void
    {
        $this->seed(SarprasPermissionSeeder::class);
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo('sarpras.gedung.manage', 'sarpras.gedung.view');

        $response = $this->actingAs($user)->post(route('admin.sarpras.gedung.store'), [
            'kode_gedung' => 'GD-B',
            'nama_gedung' => 'Gedung Barat',
            'jumlah_lantai' => 2,
        ]);

        $response->assertRedirect(route('admin.sarpras.gedung.index'));
        $this->assertDatabaseHas('gedung', ['kode_gedung' => 'GD-B', 'lembaga_id' => $lembaga->id]);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Sarpras/GedungRuanganControllerTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement FormRequests, Controllers, and Routes**
Write all requests, controllers, and register routes in `routes/admin.php`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Sarpras/GedungRuanganControllerTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Http/Requests/Sarpras/ app/Http/Controllers/ routes/ tests/Feature/Sarpras/
git commit -m "feat(sarpras): implement HTTP FormRequests, Controllers, and routes"
```

---

### Task 6: Frontend Blade Views, Modul Alpine.js, & PDF Kartu Inventaris Ruangan (KIR)

**Files:**
- Create: `resources/views/portals/lembaga/sarpras/gedung/index.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/gedung/form.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/ruangan/index.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/ruangan/form.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/ruangan/show.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/aset/index.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/aset/form.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/aset/show.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/aset/mutasi-modal.blade.php`
- Create: `resources/views/portals/lembaga/sarpras/kir/show.blade.php`
- Create: `resources/views/pdf/kartu-inventaris-ruangan.blade.php`
- Create: `resources/js/components/sarpras/aset-filter.js`
- Create: `resources/js/components/sarpras/mutasi-modal.js`
- Modify: `resources/views/layouts/sidebar.blade.php:120-170` (tambahkan navigasi menu Sarpras)
- Test: `tests/Feature/Sarpras/KirPdfExportTest.php`

- [x] **Step 1: Write failing test for KIR PDF Generation**
```php
namespace Tests\Feature\Sarpras;

use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\SarprasPermissionSeeder;
use Tests\TestCase;

class KirPdfExportTest extends TestCase
{
    public function test_can_stream_kir_pdf_for_room(): void
    {
        $this->seed(SarprasPermissionSeeder::class);
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo('sarpras.kir.export', 'sarpras.ruangan.view');

        $ruangan = Ruangan::factory()->create(['lembaga_id' => $lembaga->id]);
        AsetBarang::factory()->create(['ruangan_id' => $ruangan->id, 'lembaga_id' => $lembaga->id]);

        $response = $this->actingAs($user)->get(route('admin.sarpras.kir.export', $ruangan));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Sarpras/KirPdfExportTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Blade Views, Alpine components, PDF template, and Sidebar Menu**
Implement all Blade templates with clean modern UI, responsive tables, modal mutasi aset, and sidebar entry under `Sarpras`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Sarpras/KirPdfExportTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add resources/views/ resources/js/ tests/Feature/Sarpras/
git commit -m "feat(sarpras): implement Blade UI, Alpine.js components, KIR PDF export, and sidebar navigation"
```

---

### Task 7: Full Test Suite Verification & Handoff Audit Log

**Files:**
- Create: `.agents/logs/master-sarpras-ruangan-dan-aset.md`

- [x] **Step 1: Run Full Test Suite across all Sarpras tests**
Run: `php artisan test --filter=Sarpras`  
Expected: All tests PASS.

- [x] **Step 2: Verify Multi-Tenant & Anti-Clash Scenarios**
Run: `php artisan test --filter=Clash`  
Expected: PASS.

- [x] **Step 3: Write Handoff Audit Log**
Document summary of built features, technical decisions, and validation results at `.agents/logs/master-sarpras-ruangan-dan-aset.md`.

- [x] **Step 4: Commit**
```bash
git add .agents/logs/master-sarpras-ruangan-dan-aset.md
git commit -m "docs(sarpras): write completion handoff log for Master Sarpras"
```
