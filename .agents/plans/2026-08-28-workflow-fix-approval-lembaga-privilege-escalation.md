# Fix: Privilege Escalation Lintas-Lembaga pada Approval Workflow Generik — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) `ApproverResolverService::checkRoleApprover()` harus fail-closed terhadap aktor tanpa lembaga efektif yang jelas, ketika step approval punya target lembaga nyata. (2) `RoleController::update()` harus mencegah `scope_level` role menyimpang dari `scope_level` langkah workflow yang memakai role itu sebagai approver. (3) `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` harus memakai konsep "lembaga efektif" (bukan `user->lembaga_id` mentah) supaya aktor yayasan dengan lembaga aktif yang benar bisa lolos.

**Architecture:** 3 task berurutan (Task 1 akar masalah generik, Task 2 defense-in-depth RBAC, Task 3 penyelarasan action spesifik Rapor) — TIDAK independen sepenuhnya: Task 3 idealnya dikerjakan SETELAH Task 1 karena keduanya menyentuh alur approval yang sama (meski file berbeda, test regresi Task 3 sebagian bergantung pada perilaku resolver dari Task 1 sudah benar).

**Tech Stack:** Laravel 12, PHP 8.3, Pest v4 (untuk test Task 2/3) + PHPUnit class-based (untuk test Task 1, mengikuti konvensi `WorkflowEngineTest.php` yang sudah ada).

## Global Constraints

- Task 1 HANYA mengubah `app/Domains/Workflow/Services/ApproverResolverService.php` (method `checkRoleApprover()`).
- Task 2 HANYA mengubah `app/Http/Controllers/Admin/RoleController.php` (method `update()`, +2 import).
- Task 3 HANYA mengubah `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php` dan `VerifyPengajuanRaporAction.php`.
- **Fail-closed di Task 1 HANYA berlaku ketika ada `$targetLembagaId` nyata** (bukan null). Kalau `$targetLembagaId` null (skenario workflow generik tanpa konteks tenant, seperti di `WorkflowEngineTest.php` existing), method harus tetap `return true` seperti sebelumnya — JANGAN membuat fail-closed berlaku tanpa syarat, itu akan mematahkan test existing.
- Guard existing yang TIDAK BOLEH dihapus: guard `lembaga_id` di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction` (Task 3 memperbaikinya, BUKAN menghapusnya — lihat komentar di `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php:48-51` yang menjelaskan kenapa guard lokal ini WAJIB tetap ada sebagai defense-in-depth).
- Semua test existing di file-file yang disebut di setiap task WAJIB tetap PASS tanpa modifikasi assertion apa pun.
- Hanya jalankan test scoped per task (lihat masing-masing task). TIDAK PERLU full suite untuk setiap task individual, TAPI karena scope fix ini lintas-modul (Workflow + RBAC + Akademik), jalankan gabungan ketiga file test yang disebut plus `WorkflowEngineTest.php` di akhir Task 3 sebagai regresi gabungan (BUKAN full suite project, cukup file-file yang relevan).

---

### Task 1: Fail-Closed `ApproverResolverService::checkRoleApprover()`

**Files:**
- Modify: `app/Domains/Workflow/Services/ApproverResolverService.php`
- Create: `tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\User::widestScopeLevel(): string`, `App\Models\User::lembaga_id: ?int`, `session('active_lembaga_id')` — pola yang sama dengan `GuruController::resolveLembagaId()`/`PolaJamController::store()`.
- Produces: `ApproverResolverService::canUserApprove(WorkflowStep $step, User $user, ApprovalRequest $request): bool` — signature publik tidak berubah, hanya logika internal `checkRoleApprover()` (method `protected`) yang berubah.

- [ ] **Step 1: Baca baseline `checkRoleApprover()` untuk memastikan tidak ada drift**

Baseline (baris 25-39 di `app/Domains/Workflow/Services/ApproverResolverService.php`):

```php
    protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if (! $user->hasRole($step->approver_value)) {
            return false;
        }

        if ($step->scope_level === 'lembaga') {
            $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;
            if ($targetLembagaId && $user->lembaga_id && (int) $targetLembagaId !== (int) $user->lembaga_id) {
                return false;
            }
        }

        return true;
    }
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [ ] **Step 2: Tulis test yang gagal (reproduksi bug + regresi negatif lengkap)**

Buat file baru `tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php`, mengikuti konvensi class-based PHPUnit yang sama dengan `WorkflowEngineTest.php` yang sudah ada di direktori yang sama:

```php
<?php

namespace Tests\Unit\Domains\Workflow;

use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Domains\Workflow\Services\ApproverResolverService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApproverResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private function buatStepDanRequestUntukKelas(Kelas $kelas): array
    {
        $def = WorkflowDefinition::create([
            'code' => 'TEST_LEMBAGA_SCOPE_'.uniqid(),
            'nama_workflow' => 'Test Lembaga Scope',
            'is_active' => true,
        ]);

        $step = WorkflowStep::create([
            'workflow_definition_id' => $def->id,
            'step_number' => 1,
            'step_name' => 'Approval Lembaga',
            'approver_type' => ApproverType::Role,
            'approver_value' => 'kepala_sekolah',
            'scope_level' => 'lembaga',
            'is_final_step' => true,
        ]);

        $request = ApprovalRequest::create([
            'workflow_definition_id' => $def->id,
            'approvable_type' => $kelas->getMorphClass(),
            'approvable_id' => $kelas->id,
            'current_step_id' => $step->id,
            'status' => ApprovalStatus::Pending,
        ]);

        return [$step, $request];
    }

    public function test_denies_yayasan_scoped_user_without_active_lembaga_when_target_has_a_lembaga(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        [$step, $request] = $this->buatStepDanRequestUntukKelas($kelas);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
        $user->assignRole($roleKepsek);
        // Tanpa session('active_lembaga_id') di-set - mode "Semua Lembaga".

        $result = app(ApproverResolverService::class)->canUserApprove($step, $user, $request);

        $this->assertFalse($result);
    }

    public function test_allows_yayasan_scoped_user_with_correct_active_lembaga(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        [$step, $request] = $this->buatStepDanRequestUntukKelas($kelas);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
        $user->assignRole($roleKepsek);
        session(['active_lembaga_id' => $lembaga->id]);

        $result = app(ApproverResolverService::class)->canUserApprove($step, $user, $request);

        $this->assertTrue($result);
    }

    public function test_denies_yayasan_scoped_user_with_wrong_active_lembaga(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        [$step, $request] = $this->buatStepDanRequestUntukKelas($kelas);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $user = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
        $user->assignRole($roleKepsek);
        session(['active_lembaga_id' => $lembagaLain->id]);

        $result = app(ApproverResolverService::class)->canUserApprove($step, $user, $request);

        $this->assertFalse($result);
    }

    public function test_allows_ordinary_lembaga_scoped_user_matching_target_lembaga(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        [$step, $request] = $this->buatStepDanRequestUntukKelas($kelas);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->assignRole($roleKepsek);

        $result = app(ApproverResolverService::class)->canUserApprove($step, $user, $request);

        $this->assertTrue($result);
    }

    public function test_denies_ordinary_lembaga_scoped_user_from_a_different_lembaga(): void
    {
        $yayasan = Yayasan::factory()->create();
        $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
        $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
        $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

        [$step, $request] = $this->buatStepDanRequestUntukKelas($kelas);

        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $user = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
        $user->assignRole($roleKepsek);

        $result = app(ApproverResolverService::class)->canUserApprove($step, $user, $request);

        $this->assertFalse($result);
    }
}
```

- [ ] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php --filter="test_denies_yayasan_scoped_user_without_active_lembaga_when_target_has_a_lembaga" --compact`
Expected: FAIL — `assertFalse($result)` gagal karena `$result` aktual `true` (bug: fail-open).

Run: `php artisan test tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php --filter="test_denies_yayasan_scoped_user_with_wrong_active_lembaga" --compact`
Expected: FAIL — sama, `$result` aktual `true`.

Run 3 test lain (`test_allows_yayasan_scoped_user_with_correct_active_lembaga`, `test_allows_ordinary_lembaga_scoped_user_matching_target_lembaga`, `test_denies_ordinary_lembaga_scoped_user_from_a_different_lembaga`) dan pastikan hasilnya sesuai (2 pertama PASS dari awal karena baseline sudah `true`/mismatch handling untuk aktor biasa sudah benar; test terakhir HARUS sudah PASS juga karena baseline code sudah menangani mismatch untuk `$user->lembaga_id` yang truthy — verifikasi ini sebelum lanjut).

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Domains/Workflow/Services/ApproverResolverService.php`:

```php
    protected function checkRoleApprover(WorkflowStep $step, User $user, ApprovalRequest $request): bool
    {
        if (! $user->hasRole($step->approver_value)) {
            return false;
        }

        if ($step->scope_level === 'lembaga') {
            $targetLembagaId = $request->approvable?->lembaga_id ?? $request->requester?->lembaga_id;

            if ($targetLembagaId !== null) {
                $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
                    ? session('active_lembaga_id')
                    : $user->lembaga_id;

                if ($effectiveLembagaId === null || (int) $targetLembagaId !== (int) $effectiveLembagaId) {
                    return false;
                }
            }
        }

        return true;
    }
```

- [ ] **Step 5: Jalankan seluruh file test baru dan pastikan semua PASS**

Run: `php artisan test tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php --compact`
Expected: PASS untuk seluruh 5 test.

- [ ] **Step 6: Jalankan regresi `WorkflowEngineTest.php` existing (kritis — buktikan tidak ada regresi pada skenario tanpa konteks lembaga)**

Run: `php artisan test tests/Unit/Domains/Workflow/WorkflowEngineTest.php --compact`
Expected: PASS — `test_can_initialize_and_progress_through_multi_step_workflow` HARUS tetap lolos, membuktikan fail-closed baru tidak mematahkan skenario `$targetLembagaId === null`.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Workflow/Services/ApproverResolverService.php tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php
git commit -m "fix(workflow): fail-closed checkRoleApprover saat lembaga efektif tidak jelas"
```

---

### Task 2: Cegah `scope_level` Role Menyimpang dari `workflow_steps` di `RoleController::update()`

**Files:**
- Modify: `app/Http/Controllers/Admin/RoleController.php`
- Modify: `tests/Feature/Admin/RoleBuilderTest.php`

**Interfaces:**
- Consumes: `App\Domains\Workflow\Models\WorkflowStep::where(...)`, `App\Domains\Workflow\Enums\ApproverType::Role` — model/enum sudah ada, dipakai identik di Task 1.
- Produces: `RoleController::update()` tetap `(Request $request, Role $role): RedirectResponse|JsonResponse` — signature tidak berubah, hanya menambah 1 jalur error baru sebelum mutasi `$role`.

- [ ] **Step 1: Baca baseline `update()` untuk memastikan tidak ada drift**

Baseline (baris 138-176 di `app/Http/Controllers/Admin/RoleController.php`):

```php
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id];
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri,platform'];
        }

        $data = $request->validate($rules);

        if (! $role->is_protected) {
            $role->name = $data['name'];

            $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
            if ($this->scopeRank($data['scope_level']) > $actingRank) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
                );
            }
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil diperbarui.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

- [ ] **Step 2: Tulis test yang gagal (reproduksi bug + regresi negatif)**

Tambahkan di akhir `tests/Feature/Admin/RoleBuilderTest.php` (setelah test terakhir di file):

```php
it('rejects changing a role scope_level when it would diverge from a workflow step that uses it as approver', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'kepala_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $def = \App\Domains\Workflow\Models\WorkflowDefinition::create([
        'code' => 'TEST_ROLE_SCOPE_GUARD',
        'nama_workflow' => 'Test Role Scope Guard',
        'is_active' => true,
    ]);
    \App\Domains\Workflow\Models\WorkflowStep::create([
        'workflow_definition_id' => $def->id,
        'step_number' => 1,
        'step_name' => 'Approval Lembaga',
        'approver_type' => \App\Domains\Workflow\Enums\ApproverType::Role,
        'approver_value' => 'kepala_sekolah',
        'scope_level' => 'lembaga',
        'is_final_step' => true,
    ]);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'kepala_sekolah',
        'scope_level' => 'yayasan',
        'permissions' => [],
    ])->assertSessionHasErrors('scope_level');

    expect($role->fresh()->scope_level)->toBe('lembaga');
});

it('allows changing a role scope_level to match the workflow step scope_level it is used in', function () {
    $admin = actingAsSuperAdmin();
    $role = Role::create(['name' => 'kepala_sekolah', 'guard_name' => 'web', 'scope_level' => 'lembaga']);

    $def = \App\Domains\Workflow\Models\WorkflowDefinition::create([
        'code' => 'TEST_ROLE_SCOPE_MATCH',
        'nama_workflow' => 'Test Role Scope Match',
        'is_active' => true,
    ]);
    \App\Domains\Workflow\Models\WorkflowStep::create([
        'workflow_definition_id' => $def->id,
        'step_number' => 1,
        'step_name' => 'Approval Lembaga',
        'approver_type' => \App\Domains\Workflow\Enums\ApproverType::Role,
        'approver_value' => 'kepala_sekolah',
        'scope_level' => 'lembaga',
        'is_final_step' => true,
    ]);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'kepala_sekolah',
        'scope_level' => 'lembaga',
        'permissions' => [],
    ])->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->scope_level)->toBe('lembaga');
});
```

- [ ] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Admin/RoleBuilderTest.php --filter="rejects changing a role scope_level when it would diverge" --compact`
Expected: FAIL — `assertSessionHasErrors('scope_level')` gagal karena request justru sukses (redirect 302 tanpa errors), dan `scope_level` di DB sudah berubah jadi `yayasan`.

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Http/Controllers/Admin/RoleController.php` — tambah 2 import di puncak file:

```php
use App\Domains\Workflow\Enums\ApproverType;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Role;
```

Ganti method `update()`:

```php
    public function update(Request $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $role);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];

        if (! $role->is_protected) {
            $rules['name'] = ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id];
            $rules['scope_level'] = ['required', 'in:yayasan,lembaga,diri_sendiri,platform'];
        }

        $data = $request->validate($rules);

        if (! $role->is_protected) {
            $actingRank = $this->scopeRank($request->user()->widestScopeLevel());
            if ($this->scopeRank($data['scope_level']) > $actingRank) {
                return $this->errorResponse(
                    $request,
                    'scope_level',
                    'Anda tidak dapat mengubah role ke scope lebih luas dari scope Anda sendiri.'
                );
            }

            if ($data['scope_level'] !== $role->scope_level) {
                $dipakaiWorkflowBerbeda = WorkflowStep::where('approver_type', ApproverType::Role)
                    ->where('approver_value', $role->name)
                    ->where('scope_level', '!=', $data['scope_level'])
                    ->exists();

                if ($dipakaiWorkflowBerbeda) {
                    return $this->errorResponse(
                        $request,
                        'scope_level',
                        'Role ini dipakai sebagai approver pada langkah workflow dengan scope_level berbeda. Selaraskan scope_level langkah workflow terkait terlebih dahulu.'
                    );
                }
            }

            $role->name = $data['name'];
            $role->scope_level = $data['scope_level'];
        }

        $role->save();
        $role->syncPermissions(Permission::whereIn('id', $data['permissions'] ?? [])->get());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Role berhasil diperbarui.']);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role berhasil diperbarui.');
    }
```

**PENTING — urutan pengecekan sebelum mutasi**: cek `$dipakaiWorkflowBerbeda` memakai `$role->name`/`$role->scope_level` yang MASIH NILAI LAMA (belum di-assign `$data['name']`/`$data['scope_level']`) — pastikan baris `$role->name = ...`/`$role->scope_level = ...` tetap di BAWAH blok pengecekan ini, JANGAN dipindah ke atas seperti baseline lama.

- [ ] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Admin/RoleBuilderTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline + 2 test baru).

Jika ada test lain yang FAIL, laporkan sebagai temuan BLOCKED.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/RoleController.php tests/Feature/Admin/RoleBuilderTest.php
git commit -m "fix(rbac): cegah scope_level role menyimpang dari workflow_steps yang memakainya"
```

---

### Task 3: Perbaiki Guard Lembaga di `ApprovePengajuanRaporAction`/`VerifyPengajuanRaporAction`

**Files:**
- Modify: `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`
- Modify: `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`
- Modify: `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php`

**Interfaces:**
- Consumes: `App\Models\User::widestScopeLevel(): string`, `App\Models\User::lembaga_id: ?int`, `session('active_lembaga_id')`.
- Produces: `execute()` di kedua action tetap `(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor` — signature tidak berubah.

- [ ] **Step 1: Baca baseline kedua file untuk memastikan tidak ada drift**

Baseline `ApprovePengajuanRaporAction.php` (baris 26-32):
```php
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        if ((int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang menyetujui pengajuan rapor lembaga lain.',
            ]);
        }
```

Baseline `VerifyPengajuanRaporAction.php` (baris 26-32):
```php
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        if ((int) $pengajuanRapor->lembaga_id !== (int) $user->lembaga_id) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang memverifikasi pengajuan rapor lembaga lain.',
            ]);
        }
```

Jika file di repo berbeda dari baseline ini, STOP dan laporkan sebelum melanjutkan.

**PENTING**: JANGAN menghapus guard ini. `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php:44-53` (test `never resolves a PengajuanRapor belonging to another lembaga by id...`) secara eksplisit membuktikan guard lokal ini WAJIB ada sebagai defense-in-depth terhadap instance `PengajuanRapor` yang diteruskan langsung ke Action tanpa lewat `find()`/`TenantScope` (mis. dari command/job internal). Task ini MEMPERBAIKI logikanya, bukan menghapusnya.

- [ ] **Step 2: Tulis test yang gagal (reproduksi bug + regresi negatif)**

Tambahkan di akhir `tests/Feature/Akademik/RaporApprovalTenantScopeTest.php` (setelah test terakhir di file):

```php
it('allows a yayasan-scoped user with the correct active lembaga to verify a pengajuan rapor', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class, \Database\Seeders\WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    $roleWaka = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $userWakaYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $userWakaYayasan->assignRole($roleWaka);
    session(['active_lembaga_id' => $lembaga->id]);

    $diverifikasi = (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWakaYayasan, ApprovalAction::Approve);

    expect($diverifikasi->status)->toBe(\App\Domains\Akademik\Enums\StatusPengajuanRapor::Diverifikasi);
});

it('rejects a yayasan-scoped user without an active lembaga from verifying a pengajuan rapor', function () {
    $this->seed([\Database\Seeders\RolePermissionSeeder::class, \Database\Seeders\WorkflowDefinitionSeeder::class]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $userWali = User::factory()->create(['lembaga_id' => $lembaga->id]);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id]));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWali);

    $roleWaka = Role::firstOrCreate(['name' => 'wakasek_kurikulum', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $userWakaYayasan = User::factory()->create(['lembaga_id' => null, 'yayasan_id' => $yayasan->id]);
    $userWakaYayasan->assignRole($roleWaka);
    // Tanpa session('active_lembaga_id') - mode "Semua Lembaga".

    expect(fn () => (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWakaYayasan, ApprovalAction::Approve))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 3: Jalankan test untuk memastikan reproduksi bug GAGAL (bug masih ada)**

Run: `php artisan test tests/Feature/Akademik/RaporApprovalTenantScopeTest.php --filter="allows a yayasan-scoped user with the correct active lembaga" --compact`
Expected: FAIL — exception dilempar (guard lama selalu menolak aktor yayasan), padahal seharusnya sukses.

- [ ] **Step 4: Implementasi minimal fix**

Edit `app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php`:

```php
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $user->lembaga_id;

        if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang menyetujui pengajuan rapor lembaga lain.',
            ]);
        }
```

Edit `app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php`:

```php
    public function execute(PengajuanRapor $pengajuanRapor, User $user, ApprovalAction $action, ?string $catatan = null): PengajuanRapor
    {
        $effectiveLembagaId = $user->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $user->lembaga_id;

        if ($effectiveLembagaId === null || (int) $pengajuanRapor->lembaga_id !== (int) $effectiveLembagaId) {
            throw ValidationException::withMessages([
                'approval' => 'Anda tidak berwenang memverifikasi pengajuan rapor lembaga lain.',
            ]);
        }
```

Baris-baris lain di kedua file (setelah blok guard ini) TIDAK BERUBAH.

- [ ] **Step 5: Jalankan seluruh file test dan pastikan semua PASS**

Run: `php artisan test tests/Feature/Akademik/RaporApprovalTenantScopeTest.php --compact`
Expected: PASS untuk seluruh test di file ini (baseline 2 test + 2 test baru).

- [ ] **Step 6: Jalankan regresi gabungan seluruh file terkait**

Run: `php artisan test tests/Feature/Akademik/RaporApprovalActionsTest.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php tests/Feature/Akademik/RaporPdfDataBuilderTest.php tests/Feature/Rapor/RaporPersetujuanControllerTest.php tests/Unit/Domains/Workflow/WorkflowEngineTest.php tests/Unit/Domains/Workflow/ApproverResolverServiceTest.php tests/Feature/Admin/RoleBuilderTest.php --compact`
Expected: PASS semua — ini regresi gabungan lintas-modul untuk memastikan Task 1-3 bekerja konsisten bersama.

Jika ada test yang FAIL, laporkan sebagai temuan BLOCKED — jangan diam-diam mengubah assertion existing manapun.

- [ ] **Step 7: Jalankan Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Akademik/Actions/Rapor/ApprovePengajuanRaporAction.php app/Domains/Akademik/Actions/Rapor/VerifyPengajuanRaporAction.php tests/Feature/Akademik/RaporApprovalTenantScopeTest.php
git commit -m "fix(akademik): pakai lembaga efektif pada guard approval rapor, bukan lembaga_id mentah"
```

---

## Self-Review

**1. Spec coverage:**
- Bug 1 (§2 Fix Bug 1: fail-closed dengan syarat targetLembagaId nyata) → Task 1 Step 4. ✅
- Bug 2 (§2 Fix Bug 2: cegah scope_level menyimpang dari workflow_steps) → Task 2 Step 4. ✅
- Bug 3 (§2 Fix Bug 3: lembaga efektif di guard Rapor) → Task 3 Step 4. ✅
- §3 Non-Goals (tidak ubah ProcessApprovalAction/WorkflowStep model/checkDirectRelationApprover/AjukanIzinCutiAction/SubmitPengajuanAction/RolePolicy, tidak ada migrasi data) → tidak ada task yang menyentuh area-area itu. ✅
- §4.1 Regresi wajib → Task 1 Step 6, Task 2 Step 5, Task 3 Step 6 (regresi gabungan lintas-modul). ✅
- §4.2-4.4 (Bug 1 reproduksi + 2 varian regresi negatif + regresi positif aktor biasa) → Task 1 Step 2, 5 test mencakup semua skenario. ✅
- §4.5-4.6 (Bug 2 reproduksi + regresi negatif) → Task 2 Step 2, 2 test. ✅
- §4.7-4.8 (Bug 3 reproduksi + regresi negatif) → Task 3 Step 2, 2 test + guard existing "tanpa lembaga aktif" otomatis tercakup karena `$effectiveLembagaId === null` tetap menolak. ✅
- §5 Ringkasan file → cocok dengan Task 1-3 Files (nama file test Task 1 disesuaikan jadi `ApproverResolverServiceTest.php` sesuai spec). ✅

**2. Placeholder scan:** Tidak ada TBD/TODO. Semua kode test dan implementasi lengkap.

**3. Type consistency:** `canUserApprove()`, `RoleController::update()`, `execute()` di kedua Rapor action — semua signature publik tidak berubah di seluruh plan. Konsep `$effectiveLembagaId` dipakai identik (nama variabel dan logika) di Task 1 dan Task 3, konsisten.

---

## Konteks Tambahan untuk Kickoff

- **Urutan Task PENTING**: kerjakan Task 1 → Task 2 → Task 3 secara berurutan (bukan paralel), karena Task 3 Step 6 menjalankan regresi gabungan yang mengasumsikan Task 1 dan Task 2 sudah selesai dan commit.
- Scope fix ini LINTAS-MODUL (Workflow generik + RBAC + Akademik) — bukan modul Akademik semata. Kalau menemukan celah serupa di domain lain (Sdm/Izin-Cuti, Pengadaan/Sarpras) saat mengerjakan plan ini, JANGAN memperbaikinya sekalian — laporkan sebagai temuan terpisah di handoff log. Fix di Task 1 (`ApproverResolverService`) SUDAH otomatis berlaku untuk domain lain itu tanpa perlu sentuh kode masing-masing, jadi tidak perlu task tambahan untuk itu.
- Referensi pola "lembaga efektif" yang dipakai konsisten di Task 1 dan Task 3: `GuruController::resolveLembagaId()` (`app/Http/Controllers/Admin/GuruController.php:181-188`), `PolaJamController::store()` (fix sebelumnya di sesi ini).
