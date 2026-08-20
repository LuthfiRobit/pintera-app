<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\LpjPengadaan;
use App\Domains\Pengadaan\Models\LpjPengadaanItem;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryConversionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;
    protected Lembaga $lembaga;
    protected User $user;
    protected PengajuanPengadaan $proposal;
    protected LpjPengadaan $lpj;
    protected Ruangan $ruangan;

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
        $this->user->assignRole($role);

        $gedung = Gedung::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'kode_gedung' => 'GD-01',
            'nama_gedung' => 'Gedung Utama',
        ]);

        $this->ruangan = Ruangan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-01',
            'nama_ruangan' => 'Ruang Lab',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::Laboratorium,
        ]);

        $kategori = KategoriAset::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nama_kategori' => 'Elektronik',
            'kode_kategori' => 'ELK',
        ]);

        $this->proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/CONVERT-01',
            'judul_pengajuan' => 'Pengadaan Laptop',
            'total_estimasi' => 10000000,
            'status' => StatusPengajuan::Completed,
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'created_by_user_id' => $this->user->id,
        ]);

        $item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Laptop Asus ROG',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 10000000,
            'total_estimasi' => 10000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Approved,
        ]);

        $this->lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'total_realisasi' => 10000000,
            'selisih_dana' => 0,
            'status_lpj' => StatusLpj::Verified,
            'verified_at' => now(),
            'verified_by_user_id' => $this->user->id,
        ]);

        LpjPengadaanItem::create([
            'lpj_pengadaan_id' => $this->lpj->id,
            'pengajuan_item_id' => $item->id,
            'harga_satuan_riil' => 10000000,
            'total_riil' => 10000000,
            'status_konversi_sarpras' => 'pending',
        ]);
    }

    public function test_user_can_access_staging_and_convert_inventory(): void
    {
        // 1. Visit staging page
        $stagingResponse = $this->actingAs($this->user)->get(route('admin.pengadaan.lpj.staging-inventory', $this->lpj));
        $stagingResponse->assertOk();
        $stagingResponse->assertSee('Daftar Barang yang Siap Diterbitkan', false);

        // 2. Submit conversion
        $convertResponse = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.convert-inventory', $this->lpj), [
            'serial_numbers' => [
                $this->proposal->items->first()->id => [
                    1 => 'SN-ROG-12345',
                ],
            ],
        ]);

        $convertResponse->assertRedirect(route('admin.sarpras.aset.index'));
        $convertResponse->assertSessionHas('success');

        // 3. Verify asset is created
        $this->assertEquals(1, AsetBarang::where('ruangan_id', $this->ruangan->id)->count());
        $aset = AsetBarang::where('ruangan_id', $this->ruangan->id)->first();
        $this->assertEquals('Laptop Asus ROG', $aset->nama_barang);
        $this->assertStringContainsString('SN-ROG-12345', $aset->spesifikasi);

        // 4. Visit staging page again, should show completed state
        $revisitResponse = $this->actingAs($this->user)->get(route('admin.pengadaan.lpj.staging-inventory', $this->lpj));
        $revisitResponse->assertOk();
        $revisitResponse->assertSee('Inventarisasi Selesai Diterbitkan', false);
        $revisitResponse->assertSee('Terdaftar di Sarpras', false);
    }
}
