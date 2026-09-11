<?php

namespace Tests\Feature\Pengadaan;

use App\Domains\Pengadaan\Actions\VerifyLpjAction;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\LpjPengadaan;
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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionAssignmentSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WorkflowDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LpjValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Yayasan $yayasan;

    protected Lembaga $lembaga;

    protected User $user;

    protected Ruangan $ruangan;

    protected KategoriAset $kategori;

    protected PengajuanPengadaan $proposal;

    protected PengajuanPengadaanItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionAssignmentSeeder::class,
            WorkflowDefinitionSeeder::class,
        ]);

        Storage::fake('public');

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

        $this->kategori = KategoriAset::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nama_kategori' => 'Elektronik',
            'kode_kategori' => 'ELK',
        ]);

        $this->proposal = PengajuanPengadaan::create([
            'yayasan_id' => $this->yayasan->id,
            'lembaga_id' => $this->lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST-LPJ',
            'judul_pengajuan' => 'Pengadaan Proyektor',
            'latar_belakang' => 'KBM',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 5000000,
            'nominal_pencairan' => 5000000,
            'tanggal_pencairan' => now(),
            'status' => StatusPengajuan::Disbursed,
            'created_by_user_id' => $this->user->id,
        ]);

        $this->item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Proyektor Epson',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 5000000,
            'total_estimasi' => 5000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Approved,
        ]);
    }

    public function test_lpj_requires_scan_nota_and_foto_fisik(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 5000000,
                    'total_riil' => 5000000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'items.0.foto_nota',
            'items.0.foto_fisik',
        ]);
    }

    public function test_lpj_requires_bukti_kembali_sisa_when_there_is_remaining_cash(): void
    {
        // Realisasi 4.000.000 dari pencairan 5.000.000 -> Sisa 1.000.000
        $response = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 4000000,
                    'total_riil' => 4000000,
                    'foto_nota' => UploadedFile::fake()->create('nota.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang.jpg'),
                ],
            ],
            // Tanpa bukti_kembali_sisa
        ]);

        $response->assertSessionHasErrors(['bukti_kembali_sisa']);
    }

    public function test_lpj_can_be_submitted_with_remaining_cash_and_proof(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 4000000,
                    'total_riil' => 4000000,
                    'foto_nota' => UploadedFile::fake()->create('nota.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang.jpg'),
                ],
            ],
            'bukti_kembali_sisa' => UploadedFile::fake()->create('transfer_sisa.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('admin.pengadaan.proposal.show', $this->proposal));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lpj_pengadaan', [
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'total_realisasi' => 4000000,
            'selisih_dana' => 1000000,
            'status_lpj' => StatusLpj::Submitted->value,
        ]);
    }

    public function test_resubmit_lpj_setelah_revision_required_tidak_wajib_upload_ulang_item_yang_tidak_diubah(): void
    {
        $this->proposal->update(['nominal_pencairan' => 5500000]);

        $item2 = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $this->proposal->id,
            'kategori_aset_id' => $this->kategori->id,
            'target_ruangan_id' => $this->ruangan->id,
            'nama_barang' => 'Layar Proyektor',
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 500000,
            'total_estimasi' => 500000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Approved,
        ]);

        // Submit pertama: kedua item lengkap dengan file.
        $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 5000000,
                    'total_riil' => 5000000,
                    'foto_nota' => UploadedFile::fake()->create('nota1.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang1.jpg'),
                ],
                [
                    'pengajuan_item_id' => $item2->id,
                    'harga_satuan_riil' => 500000,
                    'total_riil' => 500000,
                    'foto_nota' => UploadedFile::fake()->create('nota2.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang2.jpg'),
                ],
            ],
        ]);

        $lpj = LpjPengadaan::where('pengajuan_pengadaan_id', $this->proposal->id)->firstOrFail();
        $itemLpjPertama = $lpj->items()->where('pengajuan_item_id', $this->item->id)->firstOrFail();
        $fotoNotaPathLama = $itemLpjPertama->foto_nota_path;
        $fotoFisikPathLama = $itemLpjPertama->foto_fisik_barang_path;
        $this->assertNotNull($fotoNotaPathLama);

        // Yayasan minta revisi.
        app(VerifyLpjAction::class)->execute($lpj, $this->user->id, false, 'Nota item 2 buram.');

        // Resubmit: HANYA item 2 yang diganti file-nya, item 1 TIDAK mengirim foto sama sekali.
        $response = $this->actingAs($this->user)->post(route('admin.pengadaan.lpj.store', $this->proposal), [
            'items' => [
                [
                    'pengajuan_item_id' => $this->item->id,
                    'harga_satuan_riil' => 5000000,
                    'total_riil' => 5000000,
                ],
                [
                    'pengajuan_item_id' => $item2->id,
                    'harga_satuan_riil' => 500000,
                    'total_riil' => 500000,
                    'foto_nota' => UploadedFile::fake()->create('nota2-ulang.pdf', 200, 'application/pdf'),
                    'foto_fisik' => UploadedFile::fake()->image('barang2-ulang.jpg'),
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.pengadaan.proposal.show', $this->proposal));
        $response->assertSessionDoesntHaveErrors();

        $itemLpjPertamaSetelahResubmit = $lpj->fresh()->items()->where('pengajuan_item_id', $this->item->id)->firstOrFail();
        $this->assertSame($fotoNotaPathLama, $itemLpjPertamaSetelahResubmit->foto_nota_path);
        $this->assertSame($fotoFisikPathLama, $itemLpjPertamaSetelahResubmit->foto_fisik_barang_path);
    }
}
