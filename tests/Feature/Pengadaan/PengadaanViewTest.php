<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PengadaanPermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengadaanViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_pengadaan_index_page(): void
    {
        $this->seed([WorkflowDefinitionSeeder::class, PengadaanPermissionSeeder::class]);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT Unggulan',
            'jenjang' => 'SMP',
            'npsn' => '11223344',
            'status_aktif' => true,
        ]);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo('pengadaan.proposal.view');

        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TESTVIEW',
            'judul_pengajuan' => 'Pengadaan Smart TV',
            'latar_belakang' => 'KBM Interaktif',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 8000000,
            'status' => StatusPengajuan::Draft,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pengadaan.proposal.index'));
        $response->assertOk();
        $response->assertSee('Pengadaan Sarana & Prasarana', false);
        $response->assertSee('Pengadaan Smart TV');
    }
}
