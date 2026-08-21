<?php

namespace Tests\Unit\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Domains\Workflow\Models\ApprovalLog;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\WorkflowStep;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_aggregates_all_lifecycle_events_in_order(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Test']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT Test',
            'npsn' => '20223344',
            'status_aktif' => true,
        ]);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id, 'name' => 'Ustadz Ahmad']);

        // 1. Create Proposal
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-01',
            'judul_pengajuan' => 'Pengadaan Komputer Lab',
            'total_estimasi' => 10000000,
            'status' => StatusPengajuan::Completed,
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'created_by_user_id' => $user->id,
            'nominal_pencairan' => 10000000,
            'tanggal_pencairan' => now()->subDays(2),
            'catatan_pencairan' => 'Transfer via BSI',
        ]);

        // 2. Approval Request & Log
        $step = WorkflowStep::first();
        $approvalReq = ApprovalRequest::create([
            'workflow_definition_id' => $step->workflow_definition_id,
            'approvable_type' => PengajuanPengadaan::class,
            'approvable_id' => $proposal->id,
            'current_step_id' => $step->id,
            'status' => \App\Domains\Workflow\Enums\ApprovalStatus::Approved,
        ]);
        ApprovalLog::create([
            'approval_request_id' => $approvalReq->id,
            'workflow_step_id' => $step->id,
            'user_id' => $user->id,
            'action' => ApprovalAction::Approve,
            'notes' => 'Disetujui untuk peningkatan lab.',
        ]);

        // 3. LPJ & Verification
        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'total_belanja_riil' => 9800000,
            'sisa_dana' => 200000,
            'status_lpj' => StatusLpj::Verified,
            'verified_by_user_id' => $user->id,
            'verified_at' => now()->subDay(),
            'catatan_verifikasi' => 'Nota lengkap dan sesuai fisik.',
        ]);

        $events = $proposal->fresh(['pengaju', 'approvalRequest.logs.user', 'approvalRequest.logs.step', 'lpj.verifiedBy'])->timelineEvents();

        $this->assertNotEmpty($events);
        $types = $events->pluck('type')->all();

        $this->assertContains('submission', $types);
        $this->assertContains('approval', $types);
        $this->assertContains('disbursement', $types);
        $this->assertContains('lpj_submission', $types);
        $this->assertContains('lpj_verification', $types);
        $this->assertContains('inventory_conversion', $types);
    }
}
