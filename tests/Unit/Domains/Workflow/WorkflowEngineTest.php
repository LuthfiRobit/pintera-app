<?php

namespace Tests\Unit\Domains\Workflow;

use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Enums\ApprovalStatus;
use App\Domains\Workflow\Models\WorkflowDefinition;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_initialize_and_progress_through_multi_step_workflow(): void
    {
        $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
        $roleYayasan = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);

        $userKepsek = User::factory()->create();
        $userKepsek->assignRole($roleKepsek);

        $userYayasan = User::factory()->create();
        $userYayasan->assignRole($roleYayasan);

        $requester = User::factory()->create();

        $def = WorkflowDefinition::create([
            'code' => 'TEST_FLOW',
            'nama_workflow' => 'Test Flow',
            'deskripsi' => 'Testing Universal Approval Workflow',
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
        $this->assertCount(2, $request->logs);
    }
}
