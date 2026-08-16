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
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\PengadaanPermissionSeeder;
use Database\Seeders\SarprasPermissionSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImagePreviewModalTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;
    protected Lembaga $lembaga;
    protected User $userAdmin;
    protected User $userAuditor;
    protected PengajuanPengadaan $proposal;
    protected LpjPengadaan $lpj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SarprasPermissionSeeder::class,
            PengadaanPermissionSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        $this->yayasan = Yayasan::create(['nama' => 'Yayasan Test']);
        $this->lembaga = Lembaga::create([
            'yayasan_id' => $this->yayasan->id,
            'nama' => 'SMP IT Test',
            'npsn' => '20223344',
            'status_aktif' => true,
        ]);

        $this->userAdmin = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin_administrasi', 'guard_name' => 'web']);
        $this->userAdmin->assignRole($roleAdmin);
        $this->userAdmin->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.proposal.create', 'pengadaan.proposal.edit']);

        $this->userAuditor = User::factory()->create(['lembaga_id' => null]);
        $roleAuditor = Role::firstOrCreate(['name' => 'bendahara_yayasan', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
        $this->userAuditor->assignRole($roleAuditor);
        $this->userAuditor->givePermissionTo(['pengadaan.proposal.view', 'pengadaan.lpj.verify', 'pengadaan.approval.yayasan']);

        $gedung = Gedung::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'kode_gedung' => 'GD-01',
            'nama_gedung' => 'Gedung Utama',
        ]);

        $ruangan = Ruangan::create([
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
            'nomor_pengajuan' => 'PR/2026/08/TEST-PREVIEW',
            'judul_pengajuan' => 'Pengadaan Smart TV',
            'latar_belakang' => 'KBM',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 8000000,
            'nominal_pencairan' => 8000000,
            'tanggal_pencairan' => now(),
            'status' => StatusPengajuan::Disbursed,
            'created_by_user_id' => $this->userAdmin->id,
        ]);

        $pItem = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $ruangan->id,
            'nama_barang' => 'Smart TV 55 Inch',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 8000000,
            'total_estimasi' => 8000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'foto_referensi_path' => 'pengadaan/acuan/tv_sample.jpg',
            'status_item' => StatusItemPengajuan::Approved,
        ]);

        $this->lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'total_realisasi' => 7500000,
            'selisih_dana' => 500000,
            'bukti_kembali_sisa_dana_path' => 'pengadaan/sisa-kas/transfer_500k.pdf',
            'status_lpj' => StatusLpj::Submitted,
        ]);

        LpjPengadaanItem::create([
            'lpj_pengadaan_id' => $this->lpj->id,
            'pengajuan_item_id' => $pItem->id,
            'harga_satuan_riil' => 7500000,
            'total_riil' => 7500000,
            'foto_nota_path' => 'pengadaan/nota/nota_tv.jpg',
            'foto_fisik_barang_path' => 'pengadaan/fisik/tv_tiba.jpg',
            'status_konversi_sarpras' => 'pending',
        ]);
    }

    public function test_audit_lpj_view_renders_image_preview_modal_triggers(): void
    {
        $response = $this->actingAs($this->userAuditor)->get(route('admin.pengadaan.audit-lpj.show', $this->lpj));
        $response->assertOk();

        // Verifikasi bahwa modal preview tersedia di layout
        $response->assertSee('$store.imagePreview.terbuka', false);
        $response->assertSee('$store.imagePreview.zoomIn()', false);
        $response->assertSee('$store.imagePreview.zoomOut()', false);
        $response->assertSee('$store.imagePreview.rotate()', false);

        // Verifikasi bahwa tombol trigger preview nota, foto fisik, dan bukti sisa ter-render
        $response->assertSee('$store.imagePreview.buka', false);
        $response->assertSee('Scan Nota - Smart TV 55 Inch', false);
        $response->assertSee('Foto Fisik - Smart TV 55 Inch', false);
        $response->assertSee('Bukti Pengembalian Sisa Kas', false);
    }

    public function test_proposal_show_view_renders_photo_referensi_modal_trigger(): void
    {
        $response = $this->actingAs($this->userAdmin)->get(route('admin.pengadaan.proposal.show', $this->proposal));
        $response->assertOk();

        $response->assertSee('$store.imagePreview.terbuka', false);
        $response->assertSee('Foto Acuan - Smart TV 55 Inch', false);
    }
}
