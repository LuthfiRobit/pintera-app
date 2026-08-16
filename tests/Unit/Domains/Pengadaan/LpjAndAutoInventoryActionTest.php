<?php

namespace Tests\Unit\Domains\Pengadaan;

use App\Domains\Pengadaan\Actions\GenerateInventoryFromLpjAction;
use App\Domains\Pengadaan\Actions\SubmitLpjPengadaanAction;
use App\Domains\Pengadaan\Actions\VerifyLpjAction;
use App\Domains\Pengadaan\DataTransferObjects\LpjPengadaanData;
use App\Domains\Pengadaan\Enums\StatusItemPengajuan;
use App\Domains\Pengadaan\Enums\StatusLpj;
use App\Domains\Pengadaan\Enums\StatusPengajuan;
use App\Domains\Pengadaan\Enums\TingkatUrgensi;
use App\Domains\Pengadaan\Models\PengajuanPengadaan;
use App\Domains\Pengadaan\Models\PengajuanPengadaanItem;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LpjAndAutoInventoryActionTest extends TestCase
{
    use RefreshDatabase;
    public function test_verified_lpj_automatically_creates_aset_barang_in_sarpras(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT',
            'jenjang' => 'SMP',
            'npsn' => (string) rand(10000000, 99999999),
            'status_aktif' => true,
        ]);
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-B',
            'nama_gedung' => 'Gedung Lab',
            'jumlah_lantai' => 2,
        ]);

        $kategori = KategoriAset::create([
            'nama_kategori' => 'Elektronik',
            'kode_kategori' => 'ELK',
            'lembaga_id' => $lembaga->id,
            'yayasan_id' => $yayasan->id,
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-LAB',
            'nama_ruangan' => 'Laboratorium Komputer',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::Laboratorium,
        ]);

        $proposal = PengajuanPengadaan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'nomor_pengajuan' => 'PR/2026/08/TEST',
            'judul_pengajuan' => 'Beli 2 Laptop Lab',
            'latar_belakang' => 'Lab Komputer',
            'tingkat_urgensi' => TingkatUrgensi::Biasa,
            'total_estimasi' => 16000000,
            'nominal_pencairan' => 16000000,
            'status' => StatusPengajuan::Disbursed,
            'created_by_user_id' => $user->id,
        ]);

        $item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $ruangan->id,
            'nama_barang' => 'Laptop Acer Core i7',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 8000000,
            'total_estimasi' => 16000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Approved,
        ]);

        $lpjData = new LpjPengadaanData(
            items: [
                [
                    'pengajuan_item_id' => $item->id,
                    'harga_satuan_riil' => 7800000,
                    'total_riil' => 15600000,
                    'foto_nota_path' => 'nota/test.jpg',
                    'foto_fisik_barang_path' => 'fisik/test.jpg',
                ]
            ],
            buktiKembaliSisaDanaPath: 'sisa/transfer.jpg'
        );

        $lpj = app(SubmitLpjPengadaanAction::class)->execute($proposal, $lpjData);
        $this->assertEquals(StatusLpj::Submitted, $lpj->status_lpj);
        $this->assertEquals(15600000, $lpj->total_realisasi);
        $this->assertEquals(400000, $lpj->selisih_dana); // Surplus 400rb

        $userYayasan = User::factory()->create();
        app(VerifyLpjAction::class)->execute($lpj, $userYayasan->id, true, 'Nota Valid & Sisa Dana Diterima');
        $this->assertEquals(StatusLpj::Verified, $lpj->refresh()->status_lpj);
        $this->assertEquals(StatusPengajuan::Completed, $proposal->refresh()->status);

        $createdAssets = app(GenerateInventoryFromLpjAction::class)->execute($lpj);
        $this->assertCount(2, $createdAssets); // 2 unit split
        $this->assertEquals(2, AsetBarang::where('ruangan_id', $ruangan->id)->count());
        $this->assertEquals('Laptop Acer Core i7', AsetBarang::first()->nama_barang);
    }
}
