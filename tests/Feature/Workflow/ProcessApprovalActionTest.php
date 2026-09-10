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
