<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Yayasan $yayasanA;

    private Lembaga $lembagaA1;

    private Lembaga $lembagaA2;

    private Lembaga $lembagaB1;

    private User $adminA1;

    private User $bendaharaA;

    private User $bendaharaB;

    private PengajuanPengadaan $proposalA2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->yayasanA = Yayasan::create(['nama' => 'Yayasan Permata']);
        $yayasanB = Yayasan::create(['nama' => 'Yayasan Cendekia']);

        $this->lembagaA1 = Lembaga::create(['yayasan_id' => $this->yayasanA->id, 'nama' => 'SDIT Permata', 'jenjang' => 'SD', 'npsn' => '111', 'status_aktif' => true]);
        $this->lembagaA2 = Lembaga::create(['yayasan_id' => $this->yayasanA->id, 'nama' => 'SMPIT Permata', 'jenjang' => 'SMP', 'npsn' => '222', 'status_aktif' => true]);
        $this->lembagaB1 = Lembaga::create(['yayasan_id' => $yayasanB->id, 'nama' => 'SDIT Cendekia', 'jenjang' => 'SD', 'npsn' => '333', 'status_aktif' => true]);

        $lembagaRole = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
        $lembagaRole->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.proposal.edit', 'pengadaan.approval.internal']);

        $yayasanRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $yayasanRole->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.approval.yayasan', 'pengadaan.disbursement.manage', 'pengadaan.lpj.verify']);

        $this->adminA1 = User::factory()->create(['lembaga_id' => $this->lembagaA1->id]);
        $this->adminA1->assignRole($lembagaRole);

        $this->bendaharaA = User::factory()->create(['lembaga_id' => null]);
        $this->bendaharaA->assignRole($yayasanRole);

        $this->bendaharaB = User::factory()->create(['lembaga_id' => null]);
        $this->bendaharaB->assignRole($yayasanRole);

        $this->proposalA2 = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasanA->id,
            'lembaga_id' => $this->lembagaA2->id,
            'nomor_pengajuan' => 'PGD-A2-001',
            'judul_pengajuan' => 'Pengadaan Rahasia Lembaga A2',
            'tingkat_urgensi' => 'biasa',
            'total_estimasi' => 1000000,
            'status' => StatusPengajuan::Submitted,
        ]);
    }

    public function test_admin_lembaga_lain_dalam_yayasan_sama_tidak_bisa_akses_proposal_single_resource(): void
    {
        // adminA1 (Lembaga A1) mencoba mengakses proposal milik Lembaga A2, meski satu yayasan.
        $this->actingAs($this->adminA1)->get(route('admin.pengadaan.proposal.show', $this->proposalA2))->assertNotFound();
        $this->actingAs($this->adminA1)->get(route('admin.pengadaan.proposal.edit', $this->proposalA2))->assertNotFound();
    }

    public function test_yayasan_approver_masih_bisa_review_proposal_dari_lembaga_manapun_dalam_yayasan_sama(): void
    {
        session(['active_lembaga_id' => $this->lembagaA1->id]);

        $response = $this->actingAs($this->bendaharaA)
            ->get(route('admin.pengadaan.inbox.review', $this->proposalA2));

        $response->assertOk();
    }

    public function test_yayasan_approver_yayasan_lain_tidak_bisa_review_atau_decision_proposal(): void
    {
        session(['active_lembaga_id' => $this->lembagaB1->id]);

        $this->actingAs($this->bendaharaB)
            ->get(route('admin.pengadaan.inbox.review', $this->proposalA2))
            ->assertNotFound();

        $this->actingAs($this->bendaharaB)
            ->post(route('admin.pengadaan.inbox.decision', $this->proposalA2), [
                'action' => ApprovalAction::Approve->value,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('pengajuan_pengadaan', [
            'id' => $this->proposalA2->id,
            'status' => StatusPengajuan::Submitted->value,
        ]);
    }

    public function test_yayasan_lain_tidak_bisa_akses_disbursement_proposal(): void
    {
        $this->proposalA2->update(['status' => StatusPengajuan::Approved]);
        session(['active_lembaga_id' => $this->lembagaB1->id]);

        $this->actingAs($this->bendaharaB)
            ->post(route('admin.pengadaan.disbursement.store', $this->proposalA2), [
                'nominal_pencairan' => 1000000,
                'tanggal_pencairan' => now()->toDateString(),
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('pengajuan_pengadaan', [
            'id' => $this->proposalA2->id,
            'nominal_pencairan' => null,
        ]);
    }

    public function test_lembaga_lain_tidak_bisa_akses_lpj_staging_dan_convert_inventory(): void
    {
        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $this->proposalA2->id,
            'status_lpj' => 'verified',
        ]);

        $adminB1 = User::factory()->create(['lembaga_id' => $this->lembagaB1->id]);
        $adminB1->givePermissionTo(['pengadaan.lpj.submit']);

        $this->actingAs($adminB1)
            ->get(route('admin.pengadaan.lpj.staging-inventory', $lpj))
            ->assertNotFound();

        $this->actingAs($adminB1)
            ->post(route('admin.pengadaan.lpj.convert-inventory', $lpj), [])
            ->assertNotFound();
    }

    public function test_yayasan_lain_tidak_bisa_akses_audit_lpj_show_dan_verify(): void
    {
        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $this->proposalA2->id,
            'status_lpj' => 'submitted',
        ]);

        session(['active_lembaga_id' => $this->lembagaB1->id]);

        $this->actingAs($this->bendaharaB)
            ->get(route('admin.pengadaan.audit-lpj.show', $lpj))
            ->assertNotFound();

        $this->actingAs($this->bendaharaB)
            ->post(route('admin.pengadaan.audit-lpj.verify', $lpj), [
                'is_approved' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('lpj_pengadaan', [
            'id' => $lpj->id,
            'status_lpj' => 'submitted',
        ]);
    }
}
