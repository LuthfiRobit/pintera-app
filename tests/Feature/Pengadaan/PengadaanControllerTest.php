<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionAssignmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengadaanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lembaga_can_create_proposal_and_submit(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionAssignmentSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidik']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT Maju',
            'jenjang' => 'SMP',
            'npsn' => '77889900',
            'status_aktif' => true,
        ]);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $user->givePermissionTo(['pengadaan.proposal.create', 'pengadaan.proposal.view']);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-1',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 2,
        ]);

        $kategori = KategoriAset::create([
            'nama_kategori' => 'IT',
            'kode_kategori' => 'IT',
            'lembaga_id' => $lembaga->id,
            'yayasan_id' => $yayasan->id,
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-1',
            'nama_ruangan' => 'Ruang Guru',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KantorGuru,
        ]);

        $response = $this->actingAs($user)->post(route('admin.pengadaan.proposal.store'), [
            'judul_pengajuan' => 'Pengadaan PC Guru',
            'latar_belakang' => 'Guru KBM',
            'tingkat_urgensi' => 'biasa',
            'items' => [
                [
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'nama_barang' => 'PC All in One',
                    'qty' => 1,
                    'satuan' => 'unit',
                    'estimasi_harga_satuan' => 9000000,
                    'tipe_pencatatan' => 'unit',
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pengajuan_pengadaan', ['judul_pengajuan' => 'Pengadaan PC Guru']);
    }
}
