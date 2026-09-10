# Workflow Terminal Status Guard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop `ProcessApprovalAction::execute()` from silently reprocessing an `ApprovalRequest` whose status is already terminal (Approved/Rejected/Cancelled/RevisionRequired), which today lets a double-submitted approval decision create duplicate `ApprovalLog` rows (all 3 domains) and duplicate `AttendanceRecord` rows (SDM).

**Architecture:** One guard clause added at the top of the shared `ProcessApprovalAction::execute()` method, before the existing `currentStep`/`canUserApprove()` checks. Because exactly 4 domain Actions call this method (Rapor's Verify/Approve, Pengadaan's ProcessProposalApproval, SDM's ProsesApprovalIzinCuti) and none of the "resubmit after rejection" flows go through it, this single change closes the gap for all 3 consuming domains without touching any domain-specific code.

**Tech Stack:** Laravel 12 / PHP 8.3, Pest.

## Global Constraints

- Fix lives in exactly one method: `ProcessApprovalAction::execute()`. Do NOT add a controller-level status guard to Rapor/Pengadaan/SDM controllers — the spec explicitly rejected that approach (root cause fix already covers all 3 domains).
- Do NOT change `current_step_id` to become null after final approval. The spec proved (via a real grep) that `admin/kehadiran-sdm/izin-cuti/show.blade.php:84` renders `$ar?->currentStep?->step_name` unconditionally to show "Langkah Tahap Saat Ini" even for already-decided requests — nulling it would silently break that UI.
- Statuses BLOCKED from reprocessing: `Approved`, `Rejected`, `Cancelled`, `RevisionRequired` (4 terminal statuses). Statuses that remain PROCESSABLE: `Pending`, `InReview` (2 active statuses). Do not invert this.
- `ApprovalAction::Cancel` never flows through `ProcessApprovalAction` at all (it's handled entirely inside `BatalkanPengajuanIzinCutiAction`, a separate code path) — no test task needs to cover Cancel through this method.
- The error message must include `$request->status->label()` verbatim as shown below — do not paraphrase it.

---

### Task 1: Core fix in `ProcessApprovalAction` + generic engine-level test

**Files:**
- Modify: `app/Domains/Workflow/Actions/ProcessApprovalAction.php`
- Test: `tests/Feature/Workflow/ProcessApprovalActionTest.php` (new file)

**Interfaces:**
- Consumes: `App\Domains\Workflow\Models\ApprovalRequest` (field: `status`, cast to `App\Domains\Workflow\Enums\ApprovalStatus`), `App\Domains\Workflow\Enums\ApprovalStatus` (cases: `Pending`, `InReview`, `Approved`, `Rejected`, `RevisionRequired`, `Cancelled`).
- Produces: `ProcessApprovalAction::execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool` — signature unchanged. Now throws `ValidationException` (key `approval`) immediately when `$request->status` is not `Pending`/`InReview`, before touching `currentStep` or `canUserApprove()`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Workflow/ProcessApprovalActionTest.php`:

```php
<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    (new RoleSeeder)->run();
});

// approvable HARUS berupa model yang punya kolom lembaga_id sah (mis. PengajuanRapor) --
// BUKAN Lembaga itu sendiri (Lembaga tidak punya kolom lembaga_id, approvable?->lembaga_id
// akan selalu null lewat magic getter Eloquent, memicu fail-closed dari perbaikan
// sebelumnya di ApproverResolverService dan membuat SEMUA panggilan gagal di
// canUserApprove() -- bukan cuma yang kedua kali yang mau diuji di sini). Pola ini SAMA
// PERSIS dengan buatStepLembagaKepsekDanRequest() di ApproverResolverServiceTest.php.
function buatRequestFinalStepUntukTerminalGuardTest(Yayasan $yayasan, Lembaga $lembaga): array
{
    $workflow = WorkflowDefinition::create([
        'code' => 'TEST_TERMINAL_GUARD',
        'nama_workflow' => 'Test Terminal Guard',
        'is_active' => true,
    ]);

    $step = WorkflowStep::create([
        'workflow_definition_id' => $workflow->id,
        'step_number' => 1,
        'step_name' => 'Verifikasi Kepala Sekolah',
        'approver_type' => ApproverType::Role,
        'approver_value' => 'kepala_sekolah',
        'scope_level' => 'lembaga',
        'is_final_step' => true,
    ]);

    $roleKepsek = Role::where('name', 'kepala_sekolah')->firstOrFail();
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole($roleKepsek);

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pengajuan = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $lembaga->id,
        'status' => StatusPengajuanRapor::Diajukan,
    ]);

    $approvalRequest = ApprovalRequest::create([
        'workflow_definition_id' => $workflow->id,
        'approvable_type' => PengajuanRapor::class,
        'approvable_id' => $pengajuan->id,
        'current_step_id' => $step->id,
        'status' => ApprovalStatus::Pending,
    ]);

    return [$user, $approvalRequest];
}

it('processes a Pending request normally (regression baseline)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    $result = app(ProcessApprovalAction::class)->execute($approvalRequest, $user, ApprovalAction::Approve, 'ok');

    expect($result)->toBeTrue();
    expect($approvalRequest->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('rejects reprocessing a request that is already Approved, without creating a duplicate ApprovalLog', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    app(ProcessApprovalAction::class)->execute($approvalRequest, $user, ApprovalAction::Approve, 'first');
    $logCountAfterFirst = ApprovalLog::where('approval_request_id', $approvalRequest->id)->count();

    expect(fn () => app(ProcessApprovalAction::class)->execute($approvalRequest->fresh(), $user, ApprovalAction::Approve, 'second'))
        ->toThrow(ValidationException::class);

    expect(ApprovalLog::where('approval_request_id', $approvalRequest->id)->count())->toBe($logCountAfterFirst);
});

it('rejects reprocessing a request that is already Rejected', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    app(ProcessApprovalAction::class)->execute($approvalRequest, $user, ApprovalAction::Reject, 'first');

    expect(fn () => app(ProcessApprovalAction::class)->execute($approvalRequest->fresh(), $user, ApprovalAction::Approve, 'second'))
        ->toThrow(ValidationException::class);
});

it('rejects reprocessing a request that is already RevisionRequired', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    app(ProcessApprovalAction::class)->execute($approvalRequest, $user, ApprovalAction::RequestRevision, 'needs changes');

    expect(fn () => app(ProcessApprovalAction::class)->execute($approvalRequest->fresh(), $user, ApprovalAction::Approve, 'second'))
        ->toThrow(ValidationException::class);
});

it('rejects reprocessing a request that is already Cancelled', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    $approvalRequest->status = ApprovalStatus::Cancelled;
    $approvalRequest->save();

    expect(fn () => app(ProcessApprovalAction::class)->execute($approvalRequest->fresh(), $user, ApprovalAction::Approve, 'second'))
        ->toThrow(ValidationException::class);
});

it('still processes an InReview request normally (multi-step workflow mid-flight)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    [$user, $approvalRequest] = buatRequestFinalStepUntukTerminalGuardTest($yayasan, $lembaga);

    $approvalRequest->status = ApprovalStatus::InReview;
    $approvalRequest->save();

    $result = app(ProcessApprovalAction::class)->execute($approvalRequest->fresh(), $user, ApprovalAction::Approve, 'ok');

    expect($result)->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Workflow/ProcessApprovalActionTest.php --compact`
Expected: the "rejects reprocessing..." tests (4 of them) FAIL — no `ValidationException` is thrown today, so `expect(fn () => ...)->toThrow(...)` fails. The "processes a Pending request normally" and "still processes an InReview request" tests already PASS (they test existing behavior).

- [ ] **Step 3: Apply the fix**

In `app/Domains/Workflow/Actions/ProcessApprovalAction.php`, change the `execute()` method from:

```php
    public function execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool
    {
        $currentStep = $request->currentStep;

        if (! $currentStep) {
            throw ValidationException::withMessages([
                'approval' => 'Permintaan persetujuan ini sudah selesai atau tidak memiliki langkah aktif.',
            ]);
        }

        if (! $this->resolverService->canUserApprove($currentStep, $user, $request)) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.',
            ]);
        }
```

to:

```php
    public function execute(ApprovalRequest $request, User $user, ApprovalAction $action, ?string $notes = null): bool
    {
        if (! in_array($request->status, [ApprovalStatus::Pending, ApprovalStatus::InReview], true)) {
            throw ValidationException::withMessages([
                'approval' => 'Permintaan persetujuan ini sudah selesai diproses ('.$request->status->label().'), tidak dapat diproses ulang.',
            ]);
        }

        $currentStep = $request->currentStep;

        if (! $currentStep) {
            throw ValidationException::withMessages([
                'approval' => 'Permintaan persetujuan ini tidak memiliki langkah aktif.',
            ]);
        }

        if (! $this->resolverService->canUserApprove($currentStep, $user, $request)) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak memiliki hak akses untuk memproses langkah persetujuan ini.',
            ]);
        }
```

(the rest of the method — the `DB::transaction(...)` block — is unchanged.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Workflow/ProcessApprovalActionTest.php --compact`
Expected: all 6 tests PASS.

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no errors.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Workflow/Actions/ProcessApprovalAction.php tests/Feature/Workflow/ProcessApprovalActionTest.php
git commit -m "fix(workflow): tolak memproses ulang ApprovalRequest yang statusnya sudah final"
```

---

### Task 2: Rapor-specific regression + new double-approve test

**Files:**
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (append test)

**Interfaces:**
- Consumes: `App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction::execute()`, `App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction::execute()`, existing test helper `siapkanAktorPersetujuan(): array` (defined at the bottom of this same file, returns `['lembaga', 'kelas', 'semester', 'siswa', 'userWaka', 'userKepsek', 'pengajuan']`).
- Produces: nothing new for later tasks — this task only adds regression coverage.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php` (before the closing of the file, after the existing `it('renders the score inside the per-mapel matrix cell...')` test):

```php
it('rejects a second Kepsek approve call on a pengajuan already Disetujui, without creating a duplicate ApprovalLog', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'userKepsek' => $userKepsek, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();
    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);
    (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve);

    $logCountAfterFirst = $pengajuan->fresh()->approvalRequest->logs()->count();

    expect(fn () => (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($pengajuan->fresh()->approvalRequest->logs()->count())->toBe($logCountAfterFirst);
});
```

This file already imports `ApprovePengajuanRaporAction`, `VerifyPengajuanRaporAction`, `ProcessApprovalAction`, `ApprovalAction`, and `WorkflowDefinitionSeeder` at the top (used by the existing tests in this file) — no new imports needed.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php --filter="rejects a second Kepsek approve"`
Expected: FAIL — before Task 1's fix, the second `execute()` call succeeds instead of throwing.

- [ ] **Step 3: Run the full Rapor regression suite**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalLockTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionGuardTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php --compact`
Expected: all tests PASS, including the new one. `SubmitPengajuanRaporActionTest.php` passing here is the concrete proof that "resubmit after Ditolak" is unaffected by Task 1's fix (that Action resets `ApprovalRequest.status` directly and never calls `ProcessApprovalAction`).

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "test(rapor): regresi double-approve pada pengajuan rapor yang sudah Disetujui"
```

---

### Task 3: Pengadaan-specific regression + new double-approve test

**Files:**
- Test: `tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php` (append test method)

**Interfaces:**
- Consumes: `App\Domains\Pengadaan\Actions\ProcessProposalApprovalAction::execute()`, `App\Domains\Pengadaan\Actions\CreatePengajuanAction::execute()`, `App\Domains\Pengadaan\Actions\SubmitPengajuanAction::execute()` — same setup pattern already used by `test_submit_partial_approval_and_disbursement_lifecycle` in this file (Yayasan/Lembaga/Gedung/KategoriAset/Ruangan fixtures, `kepala_sekolah`/`bendahara_yayasan` roles).
- Produces: nothing new for later tasks — regression coverage only.

- [ ] **Step 1: Write the failing test**

Append this method to the `PengajuanApprovalActionTest` class in `tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php` (after `test_submit_partial_approval_and_disbursement_lifecycle`, same class, same imports already present):

```php
    public function test_rejects_a_second_approve_call_on_a_proposal_already_Approved_without_duplicating_ApprovalLog(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class, WorkflowDefinitionSeeder::class]);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $roleYayasan = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Terminal Guard']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id, 'nama' => 'SMA Terminal Guard', 'jenjang' => 'SMA', 'npsn' => '99999997', 'status_aktif' => true,
        ]);

        $userPengaju = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $userKepsek = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $userKepsek->assignRole($roleKepsek);
        $userYayasan = User::factory()->create();
        $userYayasan->assignRole($roleYayasan);

        $gedung = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-TG', 'nama_gedung' => 'Gedung TG', 'jumlah_lantai' => 1]);
        $kategori = KategoriAset::create(['nama_kategori' => 'IT', 'kode_kategori' => 'IT-TG', 'lembaga_id' => $lembaga->id, 'yayasan_id' => $yayasan->id]);
        $ruangan = Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'kode_ruangan' => 'R-TG', 'nama_ruangan' => 'Ruang TG', 'lantai' => 1, 'jenis_ruangan' => JenisRuangan::KelasTeori]);

        $dto = new PengajuanPengadaanData(
            lembagaId: $lembaga->id,
            yayasanId: $yayasan->id,
            judulPengajuan: 'Terminal Guard Test',
            latarBelakang: 'Regresi double-approve',
            tingkatUrgensi: TingkatUrgensi::Mendesak,
            items: [[
                'kategori_aset_id' => $kategori->id, 'target_ruangan_id' => $ruangan->id, 'nama_barang' => 'Item TG',
                'qty' => 1, 'satuan' => 'unit', 'estimasi_harga_satuan' => 500000, 'total_estimasi' => 500000,
                'tipe_pencatatan' => TipePencatatanAset::Unit->value,
            ]]
        );

        $proposal = app(CreatePengajuanAction::class)->execute($dto, $userPengaju->id);
        app(SubmitPengajuanAction::class)->execute($proposal);
        $proposal->refresh();

        app(ProcessProposalApprovalAction::class)->execute($proposal, $userKepsek, ApprovalAction::Approve, [], 'ok');
        $proposal->refresh();
        app(ProcessProposalApprovalAction::class)->execute($proposal, $userYayasan, ApprovalAction::Approve, [], 'final ok');
        $proposal->refresh();
        $this->assertEquals(StatusPengajuan::Approved, $proposal->status);

        $logCountAfterFirst = ApprovalLog::where('approval_request_id', $proposal->approvalRequest->id)->count();

        $this->expectException(ValidationException::class);
        try {
            app(ProcessProposalApprovalAction::class)->execute($proposal->fresh(), $userYayasan, ApprovalAction::Approve, [], 'lagi');
        } finally {
            $this->assertEquals($logCountAfterFirst, ApprovalLog::where('approval_request_id', $proposal->approvalRequest->id)->count());
        }
    }
```

Add these two imports to the top of the file if not already present (check the existing `use` block first — `ApprovalLog` and `ValidationException` are likely missing since the existing test in this file doesn't reference them):

```php
use App\Domains\Workflow\Models\ApprovalLog;
use Illuminate\Validation\ValidationException;
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php --filter="rejects_a_second_approve_call"`
Expected: FAIL — before Task 1's fix, no exception is thrown, so `expectException` never gets satisfied.

- [ ] **Step 3: Run the full Pengadaan regression suite**

Run: `vendor/bin/pest tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan --compact`
Expected: all tests PASS, including `tests/Feature/Pengadaan/ProposalEditAndResubmitTest.php` — this is the concrete proof that Pengadaan's "edit and resubmit after revision" flow is unaffected (its `SubmitPengajuanAction` resets `ApprovalRequest.status` directly, never calling `ProcessApprovalAction`).

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Domains/Pengadaan/PengajuanApprovalActionTest.php
git commit -m "test(pengadaan): regresi double-approve pada proposal yang sudah Approved"
```

---

### Task 4: SDM-specific regression + new double-approve test (with AttendanceRecord check)

**Files:**
- Test: `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php` (append test)

**Interfaces:**
- Consumes: `App\Domains\Sdm\Actions\ProsesApprovalIzinCutiAction::execute()`, `App\Domains\Sdm\Actions\AjukanIzinCutiAction::execute()`, `App\Domains\Sdm\Models\AttendanceRecord` (queried by `pegawai_type`/`pegawai_id`, confirmed the correct model class — NOT a class named `AttendanceEvent`, that name only appears as the relation method `$pegawai->attendanceEvents()`), existing helper `seedIzinCutiWorkflowForTest()` (defined at the top of this same file).
- Produces: nothing new for later tasks — regression coverage only.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php` (after the existing `it('creates no AttendanceEvent when rejected', ...)` test):

```php
it('rejects a second approve call on a pengajuan already Approved, without creating a duplicate AttendanceRecord or ApprovalLog', function () {
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
    $pengajuan = app(AjukanIzinCutiAction::class)->execute($guru, KategoriPengajuanIzin::Sakit, '2026-09-01', '2026-09-01', 'Demam.');
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan, $kepsek, ApprovalAction::Approve);
    app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan->fresh(), $adminSdm, ApprovalAction::Approve);

    $recordCountAfterFirst = AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count();
    $logCountAfterFirst = $pengajuan->fresh()->approvalRequest->logs()->count();

    expect(fn () => app(ProsesApprovalIzinCutiAction::class)->execute($pengajuan->fresh(), $adminSdm, ApprovalAction::Approve))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(AttendanceRecord::where('pegawai_type', Guru::class)->where('pegawai_id', $guru->id)->count())->toBe($recordCountAfterFirst);
    expect($pengajuan->fresh()->approvalRequest->logs()->count())->toBe($logCountAfterFirst);
});
```

This file already imports `AttendanceRecord`, `AjukanIzinCutiAction`, `ProsesApprovalIzinCutiAction`, `KategoriPengajuanIzin`, `ApprovalAction`, `Guru`, `Lembaga`, `Role`, `User`, `Yayasan` at the top — no new imports needed.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php --filter="rejects a second approve call"`
Expected: FAIL — before Task 1's fix, the second call succeeds and creates a duplicate `AttendanceRecord` + `ApprovalLog` instead of throwing.

- [ ] **Step 3: Run the full SDM regression suite**

Run: `vendor/bin/pest tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --compact`
Expected: all tests PASS, including the new one.

- [ ] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Sdm/ProsesApprovalIzinCutiActionTest.php
git commit -m "test(sdm): regresi double-approve pada izin/cuti yang sudah Approved, cek AttendanceRecord tidak dobel"
```

---

### Task 5: Final full-suite verification

**Files:** none new — verification only.

**Interfaces:** none.

- [ ] **Step 1: Run the full targeted regression suite once more, all 3 domains together**

Run: `vendor/bin/pest tests/Feature/Workflow tests/Feature/Rapor tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalLockTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionGuardTest.php tests/Feature/Akademik/SubmitPengajuanRaporActionTest.php tests/Feature/Pengadaan tests/Unit/Domains/Pengadaan tests/Feature/Sdm tests/Feature/Admin/ApprovalIzinCutiControllerTest.php --compact`
Expected: all tests PASS.

- [ ] **Step 2: Run the full project test suite**

Run: `php artisan test --compact`
Expected: some failures may appear, but ONLY these 3, all pre-existing and unrelated to this change (confirmed via `git log` on each file showing no commit in this change's range touches them):
- `Tests\Unit\M3DemoDataSeederTest > it seeds a spread of pendaftaran states across K-9 institutions for man...`
- `Tests\Unit\M3DemoDataSeederTest > it is idempotent when the full DatabaseSeeder is run twice`
- `Tests\Feature\Akademik\SubjekTenantValidationTest > it rejects a komponen penilaian whose mata_pelajaran...`

If ANY OTHER test fails, STOP and investigate before proceeding — that would be a real regression from this change, not a known pre-existing failure.

- [ ] **Step 3: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no errors.

- [ ] **Step 4: Ask the user to run the complete suite**

Per project convention (Pest rules), report the full-suite result from Step 2 to the user rather than claiming final sign-off yourself.
