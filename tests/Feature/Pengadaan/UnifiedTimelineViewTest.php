<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedTimelineViewTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;
    protected Lembaga $lembaga;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $this->yayasan = Yayasan::create(['nama' => 'Yayasan Test']);
        $this->lembaga = Lembaga::create([
            'yayasan_id' => $this->yayasan->id,
            'nama' => 'SMP IT Test',
            'npsn' => '20223344',
            'status_aktif' => true,
        ]);

        $this->user = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $role = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web']);
        $role->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.proposal.create', 'pengadaan.lpj.submit']);
        $this->user->assignRole($role);
    }

    public function test_proposal_show_renders_unified_timeline_successfully(): void
    {
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-SHOW',
            'judul_pengajuan' => 'Pengadaan Smart TV Kelas',
            'total_estimasi' => 15000000,
            'status' => StatusPengajuan::Draft,
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'created_by_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.pengadaan.proposal.show', $proposal));

        $response->assertOk();
        $response->assertSee('Riwayat Aktivitas', false);
        $response->assertSee('Usulan Pengadaan Diajukan', false);
    }
}
