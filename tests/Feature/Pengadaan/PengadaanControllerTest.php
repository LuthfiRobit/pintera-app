<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Role;
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
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pengajuan_pengadaan', ['judul_pengajuan' => 'Pengadaan PC Guru']);
    }

    public function test_audit_lpj_show_tidak_menampilkan_pesan_sudah_diverifikasi_untuk_status_revision_required(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionAssignmentSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $yayasan = Yayasan::create(['nama' => 'Yayasan Audit Test']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'Sekolah Audit Test', 'npsn' => '99998888', 'status_aktif' => true]);
        $bendahara = User::factory()->create(['yayasan_id' => $yayasan->id]);
        $role = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $role->givePermissionTo(['pengadaan.lpj.verify']);
        $bendahara->assignRole($role);

        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/09/AUDIT-TEST',
            'judul_pengajuan' => 'Pengadaan Test Audit',
            'tingkat_urgensi' => 'biasa',
            'total_estimasi' => 1000000,
            'nominal_pencairan' => 1000000,
            'status' => StatusPengajuan::Disbursed,
        ]);

        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'status_lpj' => StatusLpj::RevisionRequired,
            'catatan_verifikasi' => 'Nota barang ke-2 tidak terbaca, mohon unggah ulang.',
            'verified_by_user_id' => $bendahara->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($bendahara)->get(route('admin.pengadaan.audit-lpj.show', $lpj));

        $response->assertOk();
        $response->assertDontSee('LPJ ini telah selesai diverifikasi');
        $response->assertSee('Nota barang ke-2 tidak terbaca, mohon unggah ulang.');
    }
}
