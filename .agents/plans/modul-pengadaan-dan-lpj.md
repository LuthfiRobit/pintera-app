# Modul Pengadaan Sarpras & Universal Dynamic Approval Engine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun Universal Dynamic Approval Engine (`App\Domains\Workflow\`) dan Modul Pengadaan Barang & LPJ (`App\Domains\Pengadaan\`) yang terintegrasi langsung dengan Master Sarpras untuk otomatisasi inventarisasi barang saat LPJ disetujui.

**Architecture:** Domain-Driven Design (DDD / Modular Monolith) sesuai `laravel-feature-standard`, memisahkan workflow persetujuan polimorfik universal dari domain pengadaan, menggunakan `TenantContext` untuk isolasi multi-tenant, DTOs strongly-typed, Thin Controllers, Blade Components + Alpine.js (`dataTableFilter`, `<x-modal>`, `<x-confirm-dialog>`).

**Tech Stack:** Laravel 12, PHP 8.2+, MySQL, Tailwind CSS, Alpine.js, Spatie Permission.

## Global Constraints
- Namespace Workflow: `App\Domains\Workflow\...`
- Namespace Pengadaan: `App\Domains\Pengadaan\...`
- Dynamic Approver Resolvers: Mendukung `ROLE`, `DIRECT_RELATION`, dan `SPECIFIC_USER`.
- Auto-Inventory Bridge: LPJ yang terverifikasi memanggil `CreateAsetBarangAction` dari `App\Domains\Sarpras\Actions\CreateAsetBarangAction`.
- Format Permission: `pengadaan.proposal.*`, `pengadaan.approval.*`, `pengadaan.disbursement.*`, `pengadaan.lpj.*`, `workflow.config.*`.
- Test Coverage: 100% automated test coverage pada setiap unit dan feature.

---

### Task 1: Universal Dynamic Approval Engine Database, Models & Actions

**Files:**
- Create: `app/Domains/Workflow/Enums/ApproverType.php`
- Create: `app/Domains/Workflow/Enums/ApprovalAction.php`
- Create: `app/Domains/Workflow/Enums/ApprovalStatus.php`
- Create: `app/Domains/Workflow/Models/WorkflowDefinition.php`
- Create: `app/Domains/Workflow/Models/WorkflowStep.php`
- Create: `app/Domains/Workflow/Models/ApprovalRequest.php`
- Create: `app/Domains/Workflow/Models/ApprovalLog.php`
- Create: `app/Domains/Workflow/Services/ApproverResolverService.php`
- Create: `app/Domains/Workflow/Actions/InitializeApprovalRequestAction.php`
- Create: `app/Domains/Workflow/Actions/ProcessApprovalAction.php`
- Create: `database/migrations/2026_08_16_130000_create_universal_workflow_tables.php`
- Create: `database/seeders/WorkflowDefinitionSeeder.php`
- Test: `tests/Unit/Domains/Workflow/WorkflowEngineTest.php`

**Interfaces:**
- Produces:
  - `InitializeApprovalRequestAction::execute(string $workflowCode, Model $approvable, Model $requester): ApprovalRequest`
  - `ProcessApprovalAction::execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool`
  - `ApproverResolverService::canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool`

- [x] **Step 1: Write failing test for Universal Workflow Engine**
```php
namespace Tests\Unit\Domains\Workflow;

use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    public function test_can_initialize_and_progress_through_multi_step_workflow(): void
    {
        $roleKepsek = Role::create(['name' => 'kepala_sekolah']);
        $roleYayasan = Role::create(['name' => 'bendahara_yayasan']);

        $userKepsek = User::factory()->create();
        $userKepsek->assignRole($roleKepsek);

        $userYayasan = User::factory()->create();
        $userYayasan->assignRole($roleYayasan);

        $requester = User::factory()->create();

        $def = WorkflowDefinition::create([
            'code' => 'TEST_FLOW',
            'nama_workflow' => 'Test Flow',
            'is_active' => true,
        ]);

        $step1 = WorkflowStep::create([
            'workflow_definition_id' => $def->id,
            'step_number' => 1,
            'step_name' => 'Review Kepsek',
            'approver_type' => 'ROLE',
            'approver_value' => 'kepala_sekolah',
            'scope_level' => 'lembaga',
            'is_final_step' => false,
        ]);

        $step2 = WorkflowStep::create([
            'workflow_definition_id' => $def->id,
            'step_number' => 2,
            'step_name' => 'Persetujuan Yayasan',
            'approver_type' => 'ROLE',
            'approver_value' => 'bendahara_yayasan',
            'scope_level' => 'yayasan',
            'is_final_step' => true,
        ]);

        $initAction = app(InitializeApprovalRequestAction::class);
        $request = $initAction->execute('TEST_FLOW', $requester, $requester);

        $this->assertEquals(ApprovalStatus::Pending, $request->status);
        $this->assertEquals($step1->id, $request->current_step_id);

        $processAction = app(ProcessApprovalAction::class);
        $processAction->execute($request, $userKepsek, ApprovalAction::Approve, 'Disetujui Internal');

        $request->refresh();
        $this->assertEquals(ApprovalStatus::InReview, $request->status);
        $this->assertEquals($step2->id, $request->current_step_id);

        $processAction->execute($request, $userYayasan, ApprovalAction::Approve, 'Disetujui Final');

        $request->refresh();
        $this->assertEquals(ApprovalStatus::Approved, $request->status);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Workflow/WorkflowEngineTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Enums, Migrations, Models, Resolvers, and Actions**
Implement workflow files and run migration.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Workflow/WorkflowEngineTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Workflow/ database/migrations/ database/seeders/ tests/Unit/Domains/Workflow/
git commit -m "feat(workflow): implement universal multi-scope dynamic approval workflow engine"
```

---

### Task 2: Database Schema & Models Pengadaan Sarpras (`App\Domains\Pengadaan\`)

**Files:**
- Create: `app/Domains/Pengadaan/Enums/StatusPengajuan.php`
- Create: `app/Domains/Pengadaan/Enums/TingkatUrgensi.php`
- Create: `app/Domains/Pengadaan/Enums/StatusItemPengajuan.php`
- Create: `app/Domains/Pengadaan/Enums/StatusLpj.php`
- Create: `app/Domains/Pengadaan/Models/PengajuanPengadaan.php`
- Create: `app/Domains/Pengadaan/Models/PengajuanPengadaanItem.php`
- Create: `app/Domains/Pengadaan/Models/LpjPengadaan.php`
- Create: `app/Domains/Pengadaan/Models/LpjPengadaanItem.php`
- Create: `database/migrations/2026_08_16_130100_create_pengadaan_tables.php`
- Create: `database/seeders/PengadaanPermissionSeeder.php`
- Test: `tests/Unit/Domains/Pengadaan/PengadaanModelsTest.php`

**Interfaces:**
- Produces:
  - `PengajuanPengadaan::items()` (HasMany)
  - `PengajuanPengadaan::approvalRequest()` (MorphOne)
  - `PengajuanPengadaan::lpj()` (HasOne)
  - `PengajuanPengadaanItem::kategori()` (BelongsTo KategoriAset)
  - `PengajuanPengadaanItem::ruangan()` (BelongsTo Ruangan)

- [x] **Step 1: Write failing test for Pengadaan Models and Relationships**
```php
namespace Tests\Unit\Domains\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use Tests\TestCase;

class PengadaanModelsTest extends TestCase
{
    public function test_can_create_proposal_with_items_and_relations(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $kategori = KategoriAset::create(['nama_kategori' => 'IT', 'kode_kategori' => 'IT', 'lembaga_id' => $lembaga->id]);
        $ruangan = Ruangan::factory()->create(['lembaga_id' => $lembaga->id]);

        $proposal = PengajuanPengadaan::create([
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/001',
            'judul_pengajuan' => 'Pengadaan Laptop Lab',
            'latar_belakang' => 'Kebutuhan KBM',
            'tingkat_urgensi' => TingkatUrgensi::Mendesak,
            'total_estimasi' => 15000000,
            'status' => StatusPengajuan::Draft,
            'created_by_user_id' => $user->id,
        ]);

        $item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $ruangan->id,
            'nama_barang' => 'Laptop Asus Core i5',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 7500000,
            'total_estimasi' => 15000000,
            'tipe_pencatatan' => 'unit',
        ]);

        $this->assertCount(1, $proposal->items);
        $this->assertEquals('Laptop Asus Core i5', $proposal->items->first()->nama_barang);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Pengadaan/PengadaanModelsTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Models, Enums, Migrations, and Seeders**
Write models, migrations, seeders, and run `php artisan migrate`.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Pengadaan/PengadaanModelsTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Pengadaan/ database/migrations/ database/seeders/ tests/Unit/Domains/Pengadaan/
git commit -m "feat(pengadaan): implement Pengadaan and LPJ Eloquent models, enums, and migrations"
```

---

### Task 3: DTOs & Business Actions Pengajuan, Approval & Disbursement

**Files:**
- Create: `app/Domains/Pengadaan/DataTransferObjects/PengajuanPengadaanData.php`
- Create: `app/Domains/Pengadaan/DataTransferObjects/DisbursementData.php`
- Create: `app/Domains/Pengadaan/Actions/CreatePengajuanAction.php`
- Create: `app/Domains/Pengadaan/Actions/SubmitPengajuanAction.php`
- Create: `app/Domains/Pengadaan/Actions/ProcessProposalApprovalAction.php`
- Create: `app/Domains/Pengadaan/Actions/RecordDisbursementAction.php`
- Test: `tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php`

**Interfaces:**
- Produces:
  - `CreatePengajuanAction::execute(PengajuanPengadaanData $data, int $userId): PengajuanPengadaan`
  - `SubmitPengajuanAction::execute(PengajuanPengadaan $proposal): ApprovalRequest`
  - `ProcessProposalApprovalAction::execute(PengajuanPengadaan $proposal, User $user, ApprovalAction $action, array $itemDecisions = [], ?string $notes = null): void`
  - `RecordDisbursementAction::execute(PengajuanPengadaan $proposal, DisbursementData $data): void`

- [x] **Step 1: Write failing test for Proposal Creation, Approval, and Disbursement**
```php
namespace Tests\Unit\Domains\Pengadaan;

use App\Domains\Pengadaan\Actions\CreatePengajuanAction;
use App\Domains\Pengadaan\Actions\RecordDisbursementAction;
use App\Domains\Pengadaan\Actions\SubmitPengajuanAction;
use App\Domains\Pengadaan\DataTransferObjects\DisbursementData;
use App\Domains\Pengadaan\DataTransferObjects\PengajuanPengadaanData;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\WorkflowDefinitionSeeder;
use Tests\TestCase;

class PengajuanApprovalActionTest extends TestCase
{
    public function test_submit_and_disbursement_lifecycle(): void
    {
        $this->seed(WorkflowDefinitionSeeder::class);

        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $kategori = KategoriAset::create(['nama_kategori' => 'IT', 'kode_kategori' => 'IT', 'lembaga_id' => $lembaga->id]);
        $ruangan = Ruangan::factory()->create(['lembaga_id' => $lembaga->id]);

        $dto = new PengajuanPengadaanData(
            lembagaId: $lembaga->id,
            yayasanId: $lembaga->yayasan_id,
            judulPengajuan: 'Beli Proyektor',
            latarBelakang: 'Presentasi',
            tingkatUrgensi: TingkatUrgensi::Biasa,
            items: [
                [
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'nama_barang' => 'Epson X500',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 6000000,
                    'tipe_pencatatan' => 'unit',
                ]
            ]
        );

        $proposal = app(CreatePengajuanAction::class)->execute($dto, $user->id);
        $this->assertEquals(StatusPengajuan::Draft, $proposal->status);

        app(SubmitPengajuanAction::class)->execute($proposal);
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Submitted, $proposal->status);
        $this->assertNotNull($proposal->approvalRequest);

        $disbursementData = new DisbursementData(
            nominalCair: 6000000,
            tanggalCair: now()->toDateString(),
            catatanPencairan: 'Transfer Bank BSI',
            buktiTransferPath: 'transfers/test.jpg'
        );

        app(RecordDisbursementAction::class)->execute($proposal, $disbursementData);
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Disbursed, $proposal->status);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Actions and DTOs**
Implement actions and helper classes.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Pengadaan/Actions/ app/Domains/Pengadaan/DataTransferObjects/ tests/Unit/Domains/Pengadaan/
git commit -m "feat(pengadaan): implement Proposal submission, partial approval, and disbursement actions"
```

---

### Task 4: Business Actions LPJ Settlement & Auto-Inventory Conversion to Sarpras

**Files:**
- Create: `app/Domains/Pengadaan/DataTransferObjects/LpjPengadaanData.php`
- Create: `app/Domains/Pengadaan/Actions/SubmitLpjPengadaanAction.php`
- Create: `app/Domains/Pengadaan/Actions/VerifyLpjAction.php`
- Create: `app/Domains/Pengadaan/Actions/GenerateInventoryFromLpjAction.php`
- Test: `tests/Unit/Domains/Pengadaan/LpjAndAutoInventoryActionTest.php`

**Interfaces:**
- Produces:
  - `SubmitLpjPengadaanAction::execute(PengajuanPengadaan $proposal, LpjPengadaanData $data): LpjPengadaan`
  - `VerifyLpjAction::execute(LpjPengadaan $lpj, bool $isApproved, ?string $notes = null): void`
  - `GenerateInventoryFromLpjAction::execute(LpjPengadaan $lpj, array $serialNumbers = []): Collection`

- [x] **Step 1: Write failing test for LPJ verification and auto inventory generation**
```php
namespace Tests\Unit\Domains\Pengadaan;

use App\Domains\Pengadaan\Actions\GenerateInventoryFromLpjAction;
use App\Domains\Pengadaan\Actions\SubmitLpjPengadaanAction;
use App\Domains\Pengadaan\Actions\VerifyLpjAction;
use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use Tests\TestCase;

class LpjAndAutoInventoryActionTest extends TestCase
{
    public function test_verified_lpj_automatically_creates_aset_barang_in_sarpras(): void
    {
        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $kategori = KategoriAset::create(['nama_kategori' => 'Elektronik', 'kode_kategori' => 'ELK', 'lembaga_id' => $lembaga->id]);
        $ruangan = Ruangan::factory()->create(['lembaga_id' => $lembaga->id]);

        $proposal = PengajuanPengadaan::create([
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST',
            'judul_pengajuan' => 'Beli 2 Laptop',
            'latar_belakang' => 'Lab',
            'tingkatUrgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 16000000,
            'status' => StatusPengajuan::Disbursed,
            'created_by_user_id' => $user->id,
        ]);

        $item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $ruangan->id,
            'nama_barang' => 'Laptop Acer Core i7',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 8000000,
            'total_estimasi' => 16000000,
            'tipe_pencatatan' => 'unit',
        ]);

        $lpjData = new LpjPengadaanData(
            items: [
                [
                    'pengajuan_item_id' => $item->id,
                    'harga_satuan_riil' => 7800000,
                    'total_riil' => 15600000,
                    'foto_nota_path' => 'nota/test.jpg',
                ]
            ],
            buktiKembaliSisaDanaPath: 'sisa/transfer.jpg'
        );

        $lpj = app(SubmitLpjPengadaanAction::class)->execute($proposal, $lpjData);
        $this->assertEquals(StatusLpj::Submitted, $lpj->status_lpj);
        $this->assertEquals(400000, $lpj->selisih_dana); // Surplus 400rb

        app(VerifyLpjAction::class)->execute($lpj, true, 'Nota Valid');
        $this->assertEquals(StatusLpj::Verified, $lpj->refresh()->status_lpj);

        $createdAssets = app(GenerateInventoryFromLpjAction::class)->execute($lpj);
        $this->assertCount(2, $createdAssets);
        $this->assertEquals(2, AsetBarang::where('ruangan_id', $ruangan->id)->count());
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Unit/Domains/Pengadaan/LpjAndAutoInventoryActionTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Actions and DTOs**
Implement LPJ processing and inventory generation actions.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Unit/Domains/Pengadaan/LpjAndAutoInventoryActionTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Domains/Pengadaan/Actions/ app/Domains/Pengadaan/DataTransferObjects/ tests/Unit/Domains/Pengadaan/
git commit -m "feat(pengadaan): implement LPJ submission, financial settlement, and auto-inventory conversion"
```

---

### Task 5: HTTP Layer FormRequests, Controllers, & Routes

**Files:**
- Create: `app/Http/Requests/Pengadaan/StorePengajuanRequest.php`
- Create: `app/Http/Requests/Pengadaan/ProcessApprovalRequest.php`
- Create: `app/Http/Requests/Pengadaan/StoreDisbursementRequest.php`
- Create: `app/Http/Requests/Pengadaan/StoreLpjRequest.php`
- Create: `app/Http/Controllers/Lembaga/Pengadaan/PengajuanPengadaanController.php`
- Create: `app/Http/Controllers/Lembaga/Pengadaan/LpjController.php`
- Create: `app/Http/Controllers/Yayasan/Pengadaan/ApprovalPengadaanController.php`
- Create: `app/Http/Controllers/Yayasan/Pengadaan/DisbursementPengadaanController.php`
- Create: `app/Http/Controllers/Yayasan/Pengadaan/AuditLpjController.php`
- Modify: `routes/admin.php` (register `pengadaan.*` routes)
- Test: `tests/Feature/Pengadaan/PengadaanControllerTest.php`

- [x] **Step 1: Write failing Feature test for Pengadaan Controllers**
```php
namespace Tests\Feature\Pengadaan;

use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\PengadaanPermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Tests\TestCase;

class PengadaanControllerTest extends TestCase
{
    public function test_admin_lembaga_can_create_and_submit_proposal(): void
    {
        $this->seed([WorkflowDefinitionSeeder::class, PengadaanPermissionSeeder::class]);

        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo(['pengadaan.proposal.create', 'pengadaan.proposal.view']);

        $kategori = KategoriAset::create(['nama_kategori' => 'IT', 'kode_kategori' => 'IT', 'lembaga_id' => $lembaga->id]);
        $ruangan = Ruangan::factory()->create(['lembaga_id' => $lembaga->id]);

        $response = $this->actingAs($user)->post(route('admin.pengadaan.proposal.store'), [
            'judul_pengajuan' => 'Pengadaan PC Guru',
            'latar_belakang' => 'Guru KBM',
            'tingkat_urgensi' => 'biasa',
            'items' => [
                [
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'nama_barang' => 'PC All in One',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 9000000,
                    'tipe_pencatatan' => 'unit',
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pengajuan_pengadaan', ['judul_pengajuan' => 'Pengadaan PC Guru']);
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Pengadaan/PengadaanControllerTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement FormRequests, Controllers, and Routes**
Write all requests, controllers, and routes.

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Pengadaan/PengadaanControllerTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add app/Http/Requests/Pengadaan/ app/Http/Controllers/ routes/admin.php tests/Feature/Pengadaan/
git commit -m "feat(pengadaan): implement HTTP FormRequests, Controllers, and routes"
```

---

### Task 6: Frontend Blade Views, Stepper UI, Modals & Navigation

**Files:**
- Create: `resources/views/portals/lembaga/pengadaan/proposal/index.blade.php`
- Create: `resources/views/portals/lembaga/pengadaan/proposal/_daftar.blade.php`
- Create: `resources/views/portals/lembaga/pengadaan/proposal/create.blade.php`
- Create: `resources/views/portals/lembaga/pengadaan/proposal/show.blade.php` (with lifecycle stepper)
- Create: `resources/views/portals/lembaga/pengadaan/lpj/create.blade.php`
- Create: `resources/views/portals/lembaga/pengadaan/lpj/staging-inventory.blade.php`
- Create: `resources/views/portals/yayasan/pengadaan/inbox/index.blade.php`
- Create: `resources/views/portals/yayasan/pengadaan/inbox/_daftar.blade.php`
- Create: `resources/views/portals/yayasan/pengadaan/inbox/review.blade.php`
- Create: `resources/views/portals/yayasan/pengadaan/disbursement/index.blade.php`
- Create: `resources/views/portals/yayasan/pengadaan/audit-lpj/show.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php` (tambahkan menu Pengadaan & LPJ)
- Test: `tests/Feature/Pengadaan/PengadaanViewTest.php`

- [x] **Step 1: Write failing test for Blade Views Rendering**
```php
namespace Tests\Feature\Pengadaan;

use App\Models\Lembaga;
use App\Models\User;
use Database\Seeders\PengadaanPermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Tests\TestCase;

class PengadaanViewTest extends TestCase
{
    public function test_can_render_pengadaan_index_page(): void
    {
        $this->seed([WorkflowDefinitionSeeder::class, PengadaanPermissionSeeder::class]);

        $lembaga = Lembaga::factory()->create();
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo('pengadaan.proposal.view');

        $response = $this->actingAs($user)->get(route('admin.pengadaan.proposal.index'));
        $response->assertOk();
        $response->assertSee('Pengadaan');
    }
}
```

- [x] **Step 2: Run test to verify it fails**
Run: `php artisan test tests/Feature/Pengadaan/PengadaanViewTest.php`  
Expected: FAIL.

- [x] **Step 3: Implement Blade Views, Stepper UI, Alpine Components, and Sidebar**
Create all Blade views adhering to project design system standard (`dataTableFilter`, `<x-modal>`, `<x-confirm-dialog>`, `<x-badge>`, `<x-link-button>`).

- [x] **Step 4: Run test to verify it passes**
Run: `php artisan test tests/Feature/Pengadaan/PengadaanViewTest.php`  
Expected: PASS.

- [x] **Step 5: Commit**
```bash
git add resources/views/ tests/Feature/Pengadaan/
git commit -m "feat(pengadaan): implement Blade UI views, lifecycle stepper, LPJ forms, and sidebar navigation"
```

---

### Task 7: Full Test Suite Verification & Superpowers Stage 7 Handoff Log

**Files:**
- Create: `.agents/logs/modul-pengadaan-dan-lpj.md`

- [x] **Step 1: Run Full Test Suite across Workflow, Pengadaan, and Sarpras**
Run: `php artisan test --filter=Workflow`, `php artisan test --filter=Pengadaan`, `php artisan test --filter=Sarpras`  
Expected: All tests PASS (0 regressions).

- [x] **Step 2: Write Stage 7 Handoff Audit Log**
Document summary of built features, technical decisions, and validation results at `.agents/logs/modul-pengadaan-dan-lpj.md`.

- [x] **Step 3: Commit**
```bash
git add .agents/logs/modul-pengadaan-dan-lpj.md .agents/plans/modul-pengadaan-dan-lpj.md
git commit -m "docs(pengadaan): write completion handoff log for Modul Pengadaan dan LPJ"
```
