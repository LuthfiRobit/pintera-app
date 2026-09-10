# Persetujuan Rapor & Workflow Engine Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix a systemic false-negative bug in the shared Workflow approval engine (blocks legitimate yayasan-scope approvers in aggregate mode), clean up the now-redundant duplicate guard it leaves behind in Rapor's Actions, and fix 6 smaller page-level bugs/UX gaps on the Persetujuan Rapor page.

**Architecture:** No new architecture. `ApproverResolverService::checkRoleApprover()` (in the domain-agnostic `App\Domains\Workflow` engine, shared by Akademik/Pengadaan/SDM) gets a private helper that validates `session('active_lembaga_id')` against the actor's yayasan before trusting it, instead of trusting it blindly. Rapor's two Actions (`VerifyPengajuanRaporAction`, `ApprovePengajuanRaporAction`) lose their own duplicate (buggy) copy of that same check, since the engine now does it correctly. Six independent, small fixes land on `PersetujuanController` and its views.

**Tech Stack:** Laravel 12 / PHP 8.3, Pest, Blade + Alpine.js, Spatie Laravel-permission.

## Global Constraints

- `ApproverResolverService` must NOT import or reuse `App\Domains\Akademik\Support\ResolveLembagaScopeTrait`. Workflow is a domain-agnostic engine shared by Akademik, Pengadaan, and SDM — it must not depend on any single domain's namespace. The lembaga-resolution logic is written independently, as a new private method on `ApproverResolverService` itself.
- The Item W fix must NOT loosen the aggregate-mode block itself — a yayasan-scope actor must still be required to pick an active lembaga (via the lembaga switcher) before approving a `scope_level: 'lembaga'` step. Only the validation of `session('active_lembaga_id')`'s ownership is fixed; the "must have an active lembaga" requirement stays.
- Task 2 (Item X) may only start after Task 1 (Item W) is complete and its tests pass — Task 2 removes a protective check that, while buggy, is currently the only guard covered by existing tests.
- Item B's badge markup and CSS classes must match `resources/views/admin/karyawan/index.blade.php` (lines 19-24) exactly — same class strings, same icon, same conditional structure.
- Item E must use `Rule::requiredIf(fn () => $this->input('action') === 'REJECT')` — no manual/custom validation rule.
- Every modified PHP file must pass `vendor/bin/pint --dirty --format agent` before a task is considered done.

---

### Task 1: Fix the Workflow engine root cause (Item W)

**Files:**
- Modify: `app/Domains/Workflow/Services/ApproverResolverService.php`
- Test: `tests/Feature/Workflow/ApproverResolverServiceTest.php` (new file)

**Interfaces:**
- Consumes: `App\Domains\Workflow\Models\WorkflowStep` (fields: `scope_level`, `approver_type`, `approver_value`), `App\Domains\Workflow\Models\ApprovalRequest` (relations: `approvable()`, `requester()`, both `MorphTo`), `App\Models\User::widestScopeLevel(): string`, `App\Models\Lembaga` (fields: `id`, `yayasan_id`).
- Produces: `ApproverResolverService::canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool` (signature unchanged — consumed by `App\Domains\Workflow\Actions\ProcessApprovalAction`, not modified in this task). New private method `resolveEffectiveLembagaId(User $user): ?int` on the same class — internal only, no other task depends on its name.

- [x] **Step 1: Write the failing tests**

Create `tests/Feature/Workflow/ApproverResolverServiceTest.php`:

```php
<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Domains\Workflow\Services\ApproverResolverService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function buatStepLembagaKepsekDanRequest(Lembaga $lembagaTarget): array
{
    $workflow = WorkflowDefinition::create([
        'code' => 'TEST_APPROVER_RESOLVER',
        'nama_workflow' => 'Test Approver Resolver',
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

    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTarget->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembagaTarget->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $pengajuan = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $lembagaTarget->id,
        'status' => StatusPengajuanRapor::Diajukan,
    ]);

    $approvalRequest = ApprovalRequest::create([
        'workflow_definition_id' => $workflow->id,
        'approvable_type' => PengajuanRapor::class,
        'approvable_id' => $pengajuan->id,
        'current_step_id' => $step->id,
        'status' => ApprovalStatus::InReview,
    ]);

    return [$step, $approvalRequest];
}

function buatAktorPegawaiYayasanKepsek(Yayasan $yayasan): User
{
    $roleKepsek = Role::where('name', 'kepala_sekolah')->firstOrFail();
    $rolePegawaiYayasan = Role::where('name', 'pegawai_yayasan')->firstOrFail();

    $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $user->assignRole([$roleKepsek, $rolePegawaiYayasan]);

    return $user;
}

it('denies a yayasan-scope kepala_sekolah in aggregate mode (no active lembaga chosen)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatAktorPegawaiYayasanKepsek($yayasan);
    [$step, $approvalRequest] = buatStepLembagaKepsekDanRequest($lembaga);

    session(['active_lembaga_id' => null]);

    expect((new ApproverResolverService)->canUserApprove($step, $user, $approvalRequest))->toBeFalse();
});

it('allows a yayasan-scope kepala_sekolah once their active lembaga matches the target (the bug this fixes)', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatAktorPegawaiYayasanKepsek($yayasan);
    [$step, $approvalRequest] = buatStepLembagaKepsekDanRequest($lembaga);

    session(['active_lembaga_id' => $lembaga->id]);

    expect((new ApproverResolverService)->canUserApprove($step, $user, $approvalRequest))->toBeTrue();
});

it('denies a yayasan-scope kepala_sekolah whose active lembaga belongs to a different yayasan', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatAktorPegawaiYayasanKepsek($yayasan);
    [$step, $approvalRequest] = buatStepLembagaKepsekDanRequest($lembaga);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    session(['active_lembaga_id' => $lembagaLain->id]);

    expect((new ApproverResolverService)->canUserApprove($step, $user, $approvalRequest))->toBeFalse();
});

it('denies a yayasan-scope kepala_sekolah whose active lembaga is valid but does not match the target', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $lembagaLainSatuYayasan = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = buatAktorPegawaiYayasanKepsek($yayasan);
    [$step, $approvalRequest] = buatStepLembagaKepsekDanRequest($lembaga);

    session(['active_lembaga_id' => $lembagaLainSatuYayasan->id]);

    expect((new ApproverResolverService)->canUserApprove($step, $user, $approvalRequest))->toBeFalse();
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Workflow/ApproverResolverServiceTest.php`
Expected: the "allows a yayasan-scope kepala_sekolah once their active lembaga matches the target" test FAILS (expected `true`, got `false`) — this is the bug. The other 3 tests PASS already (they assert the currently-correct "still denied" behavior), which is expected — they exist as regression guards for Step 4.

- [x] **Step 3: Fix `ApproverResolverService`**

Replace the full contents of `app/Domains/Workflow/Services/ApproverResolverService.php`:

```php
<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Lembaga;
use App\Models\User;

class ApproverResolverService
{
    public function canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if ($user->hasRole('yayasan_super_admin')) {
            return true;
        }

        return match ($step->approver_type) {
            ApproverType::Role => $this->checkRoleApprover($step, $user, $request),
            ApproverType::SpecificUser => (int) $step->approver_value === (int) $user->id,
            ApproverType::DirectRelation => $this->checkDirectRelationApprover($step, $user, $request),
        };
    }

    protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if (! $user->hasRole($step->approver_value)) {
            return false;
        }

        if ($step->scope_level === 'lembaga') {
            $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

            if ($targetLembagaId !== null) {
                $effectiveLembagaId = $this->resolveEffectiveLembagaId($user);

                if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function checkDirectRelationApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        $relationType = $step->approver_value; // e.g. wali_kelas, atasan_langsung

        if ($relationType === 'wali_kelas') {
            $siswa = $request->requester;
            if ($siswa && method_exists($siswa, 'kelasAktif')) {
                $kelas = $siswa->kelasAktif();

                return $kelas && $kelas->wali_kelas_guru_id === $user->guru?->id;
            }
        }

        return false;
    }

    /**
     * Resolusi lembaga aktif aktor yang tervalidasi -- BUKAN raw session read.
     * Untuk aktor lembaga-scope, lembaga sudah tetap (User::lembaga_id). Untuk
     * aktor yayasan-scope, session('active_lembaga_id') divalidasi dulu
     * terhadap kepemilikan yayasan sebelum dipercaya -- session stale/lintas
     * yayasan menghasilkan null, BUKAN nilai yang salah dipakai.
     *
     * Sengaja tidak reuse App\Domains\Akademik\Support\ResolveLembagaScopeTrait
     * -- Workflow adalah engine generik dipakai Akademik/Pengadaan/SDM dan
     * tidak boleh bergantung pada namespace domain manapun.
     */
    private function resolveEffectiveLembagaId(User $user): ?int
    {
        if ($user->widestScopeLevel() !== 'yayasan') {
            return $user->lembaga_id;
        }

        $lembagaId = session('active_lembaga_id');
        if ($lembagaId === null) {
            return null;
        }

        $milikYayasan = Lembaga::where('id', $lembagaId)->where('yayasan_id', $user->yayasan_id)->exists();

        return $milikYayasan ? $lembagaId : null;
    }
}
```

- [x] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Workflow/ApproverResolverServiceTest.php`
Expected: all 4 tests PASS.

- [x] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no errors, `app/Domains/Workflow/Services/ApproverResolverService.php` formatted clean.

- [x] **Step 6: Commit**

```bash
git add app/Domains/Workflow/Services/ApproverResolverService.php tests/Feature/Workflow/ApproverResolverServiceTest.php
git commit -m "fix(workflow): validasi kepemilikan yayasan atas session lembaga aktif di ApproverResolverService"
```

---

### Task 2: Remove the now-redundant duplicate guard in Rapor's Actions (Item X)

**Only start this task after Task 1 is complete and its tests pass.**

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`
- Modify: `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`
- Test (regression, not new): `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`, `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`

**Interfaces:**
- Consumes: `App\Domains\Workflow\Services\ApproverResolverService::canUserApprove()` (fixed in Task 1) via `App\Domains\Workflow\Actions\ProcessApprovalAction::execute()` (unchanged, not modified here).
- Produces: `VerifyPengajuanRaporAction::execute()` and `ApprovePengajuanRaporAction::execute()` keep their exact existing signatures — only their internal body changes. No other task depends on their internals.

- [x] **Step 1: Remove the redundant block from `VerifyPengajuanRaporAction`**

In `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`, remove this block from `execute()`:

```php
        $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $user->lembaga_id;

        if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang memverifikasi pengajuan rapor lembaga lain.',
            ]);
        }

```

The method body becomes:

```php
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $approvalRequest = $pengajuanRapor->approvalRequest;

        if (! $approvalRequest) {
            throw ValidationException::withMessages([
                'approval' => 'Pengajuan rapor ini belum pernah diajukan.',
            ]);
        }

        return DB::transaction(function () use ($pengajuanRapor, $approvalRequest, $user, $action, $catatan) {
            $pengajuanRapor = PengajuanRapor::lockForUpdate()->findOrFail($pengajuanRapor->id);

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
```

- [x] **Step 2: Remove the redundant block from `ApprovePengajuanRaporAction`**

In `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`, remove the equivalent block (same shape, message ends "...menyetujui pengajuan rapor lembaga lain."). The resulting method body mirrors Step 1's shape but keeps `ApprovalStatus::Approved` → `StatusPengajuanRapor::Disetujui` (unchanged from before).

- [x] **Step 3: Run the regression suite**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php tests/Feature/Workflow/ApproverResolverServiceTest.php`
Expected: all tests PASS (both existing files are lembaga-scope-actor tests — `siapkanAktorPersetujuan()` creates `$userWaka`/`$userKepsek` with `lembaga_id` set directly, so `widestScopeLevel()` returns `'lembaga'` for them and they are unaffected by this change; they must keep passing unchanged).

- [x] **Step 4: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 5: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php
git commit -m "refactor(rapor): hapus guard lembaga duplikat di Verify/ApprovePengajuanRaporAction, sudah ditangani ApproverResolverService"
```

---

### Task 3: Fix the redundant unvalidated filter in the "riwayat" tab (Item A)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Test: `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`

**Interfaces:**
- Consumes: none new.
- Produces: `PersetujuanController::index()` behavior unchanged from the caller's perspective (same route, same view data keys `pengajuanList`, `tab`).

**Note on TDD shape for this task:** Item A removes redundant/misleading code rather than fixing a currently-observable wrong result. The manual filter's `->when($effectiveLembagaId, ...)` only applies a `where` clause when `$effectiveLembagaId` is truthy — when it resolves to `null` (the stale-session case), the clause is skipped entirely and `TenantScope` alone already governs the query correctly. So there is no red test to write here: this is a refactor-only step (dead/misleading code removal), verified by the existing regression test, not a new failing test.

- [x] **Step 1: Make the edit**

In `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, replace the `index()` method's `if ($tab === 'riwayat')` branch:

```php
        if ($tab === 'riwayat') {
            $query = PengajuanRapor::whereIn('status', [StatusPengajuanRapor::Disetujui, StatusPengajuanRapor::Ditolak])
                ->with(['kelas.tahunAjaran', 'semester'])
                ->when($request->search, function ($q, $search) {
                    $q->whereHas('kelas', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
                })
                ->latest();
        } else {
```

(the `else` branch and everything after stays unchanged.)

- [x] **Step 2: Run the existing regression test**

Run: `vendor/bin/pest tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`
Expected: PASS (the pre-existing test `it('menampilkan pengajuan yang sudah Disetujui di tab riwayat, bukan tab default')` still passes unchanged).

- [x] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 4: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php
git commit -m "refactor(rapor): hapus filter manual redundan di tab riwayat persetujuan, TenantScope sudah menangani"
```

---

### Task 4: Add the yayasan/lembaga scope badge to the header (Item B)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`

**Interfaces:**
- Consumes: `User::widestScopeLevel()`, `Lembaga::withoutGlobalScopes()->find()`.
- Produces: `PersetujuanController::index()` view data gains two new keys, `isYayasan` (bool) and `activeLembaga` (`?Lembaga`) — Task 3 and Task 6 do not read these, no conflict.

- [x] **Step 1: Write the failing test**

Append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
it('shows the yayasan scope badge for a yayasan-scope actor with no active lembaga', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    $yayasan = Yayasan::factory()->create();
    $role = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rapor.verify', 'guard_name' => 'web']);
    $role->givePermissionTo('rapor.verify');
    $rolePegawaiYayasan = Role::firstOrCreate(['name' => 'pegawai_yayasan', 'guard_name' => 'web']);

    $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $user->assignRole([$role, $rolePegawaiYayasan]);

    session(['active_lembaga_id' => null]);

    $response = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index'));

    $response->assertOk();
    $response->assertSee('Semua Lembaga');
});

it('does not show the scope badge for a lembaga-scope actor', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.index'));

    $response->assertOk();
    $response->assertDontSee('Semua Lembaga');
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php --filter="scope badge"`
Expected: FAIL — `assertSee('Semua Lembaga')` finds nothing, the badge markup doesn't exist yet.

- [x] **Step 3: Add `scopeHeaderData()` to the controller and wire it into `index()`**

In `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, add the `use App\Models\Lembaga;` import at the top, then add this private method:

```php
    /**
     * Info scope yayasan/lembaga yang sedang aktif, ditampilkan sebagai badge di header
     * halaman (pola sama seperti admin/karyawan/index.blade.php) -- HANYA relevan untuk aktor
     * berscope yayasan (punya switcher lembaga); aktor lembaga-scope tidak butuh badge ini
     * karena mereka selalu berada di 1 lembaga tetap.
     *
     * @return array{isYayasan: bool, activeLembaga: ?Lembaga}
     */
    private function scopeHeaderData(Request $request): array
    {
        $isYayasan = $request->user()->widestScopeLevel() === 'yayasan';
        $lembagaId = session('active_lembaga_id');

        return [
            'isYayasan' => $isYayasan,
            'activeLembaga' => ($isYayasan && $lembagaId) ? Lembaga::withoutGlobalScopes()->find($lembagaId) : null,
        ];
    }
```

Change the end of `index()` from:

```php
        return view('portals.lembaga.rapor.persetujuan.index', compact('pengajuanList', 'tab'));
```

to:

```php
        return view('portals.lembaga.rapor.persetujuan.index', array_merge(
            compact('pengajuanList', 'tab'),
            $this->scopeHeaderData($request)
        ));
```

- [x] **Step 4: Add the badge markup to the view**

In `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`, replace lines 7-15:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Rapor</h1>
                <p class="text-xs text-gray-500 mt-0.5">Daftar kelas yang menunggu keputusan Anda pada alur persetujuan rapor semester.</p>
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Rapor</b>
            </p>
        </div>
```

with:

```blade
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-lg font-bold text-gray-900">Persetujuan Rapor</h1>
                    @if ($isYayasan ?? false)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ ($activeLembaga ?? null) ? 'border border-brand-200 bg-brand-50 text-brand-700' : 'border border-purple-200 bg-purple-50 text-purple-700' }}">
                            <x-icon name="apartment" class="h-3.5 w-3.5" />
                            {{ ($activeLembaga ?? null) ? $activeLembaga->nama : 'Semua Lembaga' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-0.5">Daftar kelas yang menunggu keputusan Anda pada alur persetujuan rapor semester.</p>
            </div>
            <p class="text-sm text-gray-500">
                Akademik <span class="mx-1 text-gray-300">&rsaquo;</span> <b class="font-semibold text-gray-700">Persetujuan Rapor</b>
            </p>
        </div>
```

- [x] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: all tests PASS, including the 2 new ones.

- [x] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php resources/views/portals/lembaga/rapor/persetujuan/index.blade.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "feat(rapor): tambah badge scope yayasan/lembaga di header Persetujuan Rapor"
```

---

### Task 5: Show Tahun Ajaran next to Semester (Item C)

**Files:**
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`

**Interfaces:**
- Consumes: `$pengajuan->kelas->tahunAjaran` / `$pengajuanRapor->kelas->tahunAjaran` (already eager-loaded by the controller, both `index()` and `show()`; no controller change needed in this task).
- Produces: no new interface — pure view output change.

- [x] **Step 1: Write the failing test**

Append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
it('shows the tahun ajaran name next to semester on the index list', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'kelas' => $kelas] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.index'));

    $response->assertOk();
    $response->assertSee($kelas->tahunAjaran->nama);
});

it('shows the tahun ajaran name next to semester on the show page', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'kelas' => $kelas, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.show', $pengajuan));

    $response->assertOk();
    $response->assertSee($kelas->tahunAjaran->nama);
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php --filter="tahun ajaran"`
Expected: FAIL — Tahun Ajaran name is not rendered anywhere yet.

- [x] **Step 3: Edit `_daftar.blade.php`**

In `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`, change line 19 from:

```blade
                        <td class="px-5 py-3.5 text-gray-600">{{ $pengajuan->semester->nama }}</td>
```

to:

```blade
                        <td class="px-5 py-3.5 text-gray-600">{{ $pengajuan->semester->nama }} — {{ $pengajuan->kelas->tahunAjaran->nama }}</td>
```

- [x] **Step 4: Edit `show.blade.php`**

In `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`, change line 10 from:

```blade
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Semester: {{ $pengajuanRapor->semester->nama }}</p>
```

to:

```blade
                <p class="text-xs text-gray-500 mt-0.5 font-mono">Semester: {{ $pengajuanRapor->semester->nama }} — {{ $pengajuanRapor->kelas->tahunAjaran->nama }}</p>
```

- [x] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: all tests PASS.

- [x] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 7: Commit**

```bash
git add resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php resources/views/portals/lembaga/rapor/persetujuan/show.blade.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "feat(rapor): tampilkan Tahun Ajaran di samping Semester pada Persetujuan Rapor"
```

---

### Task 6: Fix the untabbed `$tab` variable and the wrong empty-state message (Item D)

**Files:**
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`
- Test: `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`

**Interfaces:**
- Consumes: `$tab` string (`'menunggu'` or `'riwayat'`), already present in `index.blade.php`'s scope (passed by the controller) and now also passed explicitly into the `_daftar` partial.
- Produces: no new interface.

- [x] **Step 1: Write the failing test**

Append to `tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`:

```php
it('shows a riwayat-specific empty state message on the riwayat tab, not the menunggu-keputusan wording', function () {
    Permission::firstOrCreate(['name' => 'rapor.approve', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('rapor.approve');

    $response = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index', ['tab' => 'riwayat']));

    $response->assertOk();
    $response->assertSee('Belum ada riwayat keputusan persetujuan rapor.');
    $response->assertDontSee('Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.');
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php --filter="riwayat-specific"`
Expected: FAIL — the "menunggu keputusan" message is shown regardless of tab, since `$tab` never reaches the partial.

- [x] **Step 3: Pass `tab` into the partial include**

In `resources/views/portals/lembaga/rapor/persetujuan/index.blade.php`, change line 44 from:

```blade
                @include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList])
```

to:

```blade
                @include('portals.lembaga.rapor.persetujuan._daftar', ['pengajuanList' => $pengajuanList, 'tab' => $tab])
```

- [x] **Step 4: Condition the empty-state message on `$tab`**

In `resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php`, change line 26 from:

```blade
                            Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.
```

to:

```blade
                            {{ ($tab ?? 'menunggu') === 'riwayat' ? 'Belum ada riwayat keputusan persetujuan rapor.' : 'Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.' }}
```

(`?? 'menunggu'` default keeps the partial safe if any other caller ever includes it without passing `tab`.)

- [x] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php`
Expected: all tests PASS.

- [x] **Step 6: Run Pint**

Not applicable (Blade-only change, no PHP files modified).

- [x] **Step 7: Commit**

```bash
git add resources/views/portals/lembaga/rapor/persetujuan/index.blade.php resources/views/portals/lembaga/rapor/persetujuan/_daftar.blade.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php
git commit -m "fix(rapor): teruskan variabel tab ke partial daftar, pesan empty-state sesuai konteks tab"
```

---

### Task 7: Require `catatan` when rejecting (Item E)

**Files:**
- Modify: `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php`
- Modify: `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`

**Interfaces:**
- Consumes: none new.
- Produces: `ProcessRaporApprovalRequest::rules()` return shape unchanged (`array<string, array<int, mixed>>`), only the `catatan` rule set gains a conditional entry — `PersetujuanController::decision()` (not modified in this task) already reads `$request->validated('catatan')` and is unaffected.

- [x] **Step 1: Write the failing test**

Append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
it('requires catatan when rejecting a pengajuan rapor', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'REJECT'])
        ->assertSessionHasErrors('catatan');
});

it('does not require catatan when approving a pengajuan rapor', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan] = siapkanAktorPersetujuan();

    $this->actingAs($userWaka)
        ->post(route('admin.rapor.persetujuan.decision', $pengajuan), ['action' => 'APPROVE'])
        ->assertSessionDoesntHaveErrors('catatan');
});
```

- [x] **Step 2: Run tests to verify the REJECT one fails**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php --filter="requires catatan|does not require catatan"`
Expected: "requires catatan when rejecting" FAILS (no validation error is currently raised for a REJECT with no `catatan`). "does not require catatan when approving" already PASSES.

- [x] **Step 3: Update the FormRequest**

Replace the full contents of `app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProcessRaporApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['rapor.verify', 'rapor.approve']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['APPROVE', 'REJECT'])],
            'catatan' => [
                Rule::requiredIf(fn () => $this->input('action') === 'REJECT'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
```

- [x] **Step 4: Update the label in `show.blade.php` to react to the selected action**

In `resources/views/portals/lembaga/rapor/persetujuan/show.blade.php`, change line 139 from:

```blade
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan (Opsional)</label>
```

to:

```blade
                    <label class="block text-xs font-semibold text-gray-700 mb-1" x-text="action === 'REJECT' ? 'Catatan (Wajib diisi untuk penolakan)' : 'Catatan (Opsional)'"></label>
```

(The enclosing `<form>` on line 125 already has `x-data="{ action: 'APPROVE' }"` and the radio inputs already `x-model="action"` — no Alpine wiring changes needed beyond this label.)

- [x] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: all tests PASS, including the pre-existing `it('lets Waka reject, setting status to Ditolak with catatan_revisi', ...)` test (line 165-178 in the current file), which already sends a non-empty `catatan` on REJECT and is therefore unaffected.

- [x] **Step 6: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 7: Commit**

```bash
git add app/Http/Requests/Akademik/ProcessRaporApprovalRequest.php resources/views/portals/lembaga/rapor/persetujuan/show.blade.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "feat(rapor): wajibkan catatan saat menolak pengajuan rapor"
```

---

### Task 8: Eager-load `kelas.lembaga` in `cetak()` (Item F)

**Files:**
- Modify: `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`
- Test: `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`

**Interfaces:**
- Consumes: `PengajuanRapor::loadMissing()` (standard Eloquent method).
- Produces: no new interface — `cetak()`'s signature and behavior are unchanged, this is a pure query-count optimization.

- [x] **Step 1: Write the failing test**

Append to `tests/Feature/Rapor/RaporPersetujuanControllerTest.php`:

```php
it('eager-loads kelas.lembaga in cetak() to avoid lazy-loading', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['userWaka' => $userWaka, 'pengajuan' => $pengajuan, 'siswa' => $siswa] = siapkanAktorPersetujuan();

    \Illuminate\Database\Eloquent\Model::preventLazyLoading();

    try {
        $response = $this->actingAs($userWaka)->get(route('admin.rapor.persetujuan.cetak', ['pengajuanRapor' => $pengajuan->id, 'siswa' => $siswa->id]));
        $response->assertOk();
    } finally {
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php --filter="eager-loads kelas.lembaga"`
Expected: FAIL — a `Illuminate\Database\LazyLoadingViolationException` is thrown when `$pengajuanRapor->kelas->lembaga->bentuk_pendidikan` triggers lazy-loading (`kelas` and `kelas.lembaga` are both unloaded in `cetak()` today).

- [x] **Step 3: Add the eager-load**

In `app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php`, change the `cetak()` method from:

```php
    public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): Response
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

        $data = $this->raporPdfDataBuilder->build($siswa, $pengajuanRapor->semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($pengajuanRapor->kelas->lembaga->bentuk_pendidikan);
```

to:

```php
    public function cetak(PengajuanRapor $pengajuanRapor, Siswa $siswa, Request $request): Response
    {
        abort_unless($request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
        abort_unless($siswa->kelas_id === $pengajuanRapor->kelas_id, 404);

        $pengajuanRapor->loadMissing('kelas.lembaga');

        $data = $this->raporPdfDataBuilder->build($siswa, $pengajuanRapor->semester);
        $template = $this->raporPdfDataBuilder->templateUntukJenjang($pengajuanRapor->kelas->lembaga->bentuk_pendidikan);
```

(the rest of the method — `Pdf::loadView(...)` and the `return` — is unchanged.)

- [x] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php`
Expected: all tests PASS.

- [x] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [x] **Step 6: Commit**

```bash
git add app/Http/Controllers/Lembaga/Rapor/PersetujuanController.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php
git commit -m "perf(rapor): eager-load kelas.lembaga di cetak() untuk hindari lazy-load"
```

---

### Task 9: Final full regression + Pint

**Files:** none new — verification only.

**Interfaces:** none.

- [x] **Step 1: Run the full targeted regression suite**

Run: `vendor/bin/pest tests/Feature/Rapor/RaporPersetujuanControllerTest.php tests/Feature/Akademik/PersetujuanRaporRiwayatTest.php tests/Feature/Workflow/ApproverResolverServiceTest.php --compact`
Expected: all tests across all 3 files PASS.

- [x] **Step 2: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no errors.

- [x] **Step 3: Ask the user to run the complete suite**

Per project convention (Pest rules), ask the user to run `php artisan test --compact` for the full project suite before merge — do not run it automatically as part of this task.
