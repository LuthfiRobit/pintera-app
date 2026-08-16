<?php

namespace Tests\Unit\Domains\Pengadaan;

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
use App\Models\User;
use App\Models\Yayasan;
use Tests\TestCase;

class PengadaanModelsTest extends TestCase
{
    public function test_can_create_proposal_with_items_and_relations(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SMP IT',
            'jenjang' => 'SMP',
            'npsn' => '88888888',
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
            'nama_kategori' => 'IT',
            'kode_kategori' => 'IT',
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
            'nomor_pengajuan' => 'PR/2026/08/001',
            'judul_pengajuan' => 'Pengadaan Laptop Lab',
            'latar_belakang' => 'Kebutuhan KBM',
            'tingkat_urgensi' => TingkatUrgensi::Mendesak,
            'total_estimasi' => 15000000,
            'status' => StatusPengajuan::Draft,
            'created_by_user_id' => $user->id,
        ]);

        $item = PengajuanPengadaanItem::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'kategori_aset_id' => $kategori->id,
            'target_ruangan_id' => $ruangan->id,
            'nama_barang' => 'Laptop Asus Core i5',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga_satuan' => 7500000,
            'total_estimasi' => 15000000,
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'status_item' => StatusItemPengajuan::Pending,
        ]);

        $this->assertCount(1, $proposal->items);
        $this->assertEquals('Laptop Asus Core i5', $proposal->items->first()->nama_barang);
        $this->assertEquals($kategori->id, $proposal->items->first()->kategori->id);
        $this->assertEquals($ruangan->id, $proposal->items->first()->ruangan->id);

        $lpj = LpjPengadaan::create([
            'pengajuan_pengadaan_id' => $proposal->id,
            'total_realisasi' => 15000000,
            'selisih_dana' => 0,
            'status_lpj' => StatusLpj::Draft,
        ]);

        $lpjItem = LpjPengadaanItem::create([
            'lpj_pengadaan_id' => $lpj->id,
            'pengajuan_item_id' => $item->id,
            'harga_satuan_riil' => 7500000,
            'total_riil' => 15000000,
            'status_konversi_sarpras' => 'pending',
        ]);

        $this->assertEquals($proposal->id, $lpj->proposal->id);
        $this->assertCount(1, $lpj->items);
    }
}
