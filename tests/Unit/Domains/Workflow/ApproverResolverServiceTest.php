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
