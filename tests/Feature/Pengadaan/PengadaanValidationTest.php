<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
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

class PengadaanValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;
    protected Lembaga $lembaga;
    protected User $admUser;
    protected User $bendaharaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionAssignmentSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $this->yayasan = Yayasan::create(['nama' => 'Yayasan Test']);
        $this->lembaga = Lembaga::create([
            'yayasan_id' => $this->yayasan->id,
            'nama' => 'SMP IT Test',
            'npsn' => '20223344',
            'status_aktif' => true,
        ]);

        $this->admUser = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $admRole = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web']);
        $admRole->givePermissionTo(['pengadaan.proposal.create', 'pengadaan.proposal.view', 'pengadaan.lpj.submit']);
        $this->admUser->assignRole($admRole);

        $this->bendaharaUser = User::factory()->create(['lembaga_id' => null]);
        $bendaharaRole = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web']);
        $this->bendaharaUser->assignRole($bendaharaRole);
    }

    public function test_cannot_create_proposal_without_items(): void
    {
        $response = $this->actingAs($this->admUser)->post(route('admin.pengadaan.proposal.store'), [
            'judul_pengajuan' => '',
            'tingkat_urgensi' => '',
            'items' => [],
        ]);

        $response->assertSessionHasErrors(['judul_pengajuan', 'tingkat_urgensi', 'items']);
    }

    public function test_cannot_create_proposal_with_invalid_item_qty_or_price(): void
    {
        $kategori = KategoriAset::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nama_kategori' => 'Elektronik',
            'kode_kategori' => 'ELK',
        ]);
        $gedung = \App\Domains\Sarpras\Models\Gedung::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nama_gedung' => 'Gedung Utama',
            'kode_gedung' => 'G01',
        ]);
        $ruangan = Ruangan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'gedung_id' => $gedung->id,
            'nama_ruangan' => 'Lab Komputer',
            'kode_ruangan' => 'LAB-01',
        ]);

        $response = $this->actingAs($this->admUser)->post(route('admin.pengadaan.proposal.store'), [
            'judul_pengajuan' => 'Pengadaan Komputer',
            'tingkat_urgensi' => 'biasa',
            'items' => [
                [
                    'nama_barang' => '',
                    'kategori_aset_id' => $kategori->id,
                    'target_ruangan_id' => $ruangan->id,
                    'qty' => 0, // invalid min:1
                    'satuan' => '',
                    'estimasi_harga_satuan' => -500, // invalid min:0
                    'tipe_pencatatan' => 'invalid_type',
                ]
            ],
        ]);

        $response->assertSessionHasErrors([
            'items.0.nama_barang',
            'items.0.qty',
            'items.0.satuan',
            'items.0.estimasi_harga_satuan',
            'items.0.tipe_pencatatan',
        ]);
    }

    public function test_disbursement_requires_valid_nominal_and_date(): void
    {
        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-01',
            'judul_pengajuan' => 'Pengadaan Laptop',
            'total_estimasi' => 10000000,
            'status' => StatusPengajuan::Approved,
            'tingkat_urgensi' => 'biasa',
        ]);

        $response = $this->actingAs($this->bendaharaUser)->post(route('admin.pengadaan.disbursement.store', $proposal), [
            'nominal_pencairan' => 0, // invalid min:1
            'tanggal_pencairan' => 'invalid-date',
        ]);

        $response->assertSessionHasErrors(['nominal_pencairan', 'tanggal_pencairan']);
    }
}
