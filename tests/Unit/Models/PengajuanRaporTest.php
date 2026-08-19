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
