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
    $user = buatAktorPegawaiYayasanKepsek($yayasan);

    $yayasanLain = Yayasan::factory()->create();
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasanLain->id]);
    [$step, $approvalRequest] = buatStepLembagaKepsekDanRequest($lembagaLain);
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
