<?php

namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Sarpras\Actions\CreateAsetBarangAction;
use App\Domains\Sarpras\Actions\CreateKategoriAsetAction;
use App\Domains\Sarpras\Actions\MutasiAsetRuanganAction;
use App\Domains\Sarpras\Actions\UpdateAsetBarangAction;
use App\Domains\Sarpras\DataTransferObjects\AsetBarangData;
use App\Domains\Sarpras\DataTransferObjects\KategoriAsetData;
use App\Domains\Sarpras\DataTransferObjects\MutasiAsetData;
use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
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

class AsetMutasiActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_kategori_and_aset_action(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);
        $gedung = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-1', 'nama_gedung' => 'Gedung 1']);
        $ruangan = Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'kode_ruangan' => 'R-1', 'nama_ruangan' => 'Ruang 1']);

        $katDto = new KategoriAsetData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            kodeKategori: 'MEB',
            namaKategori: 'Mebel & Furnitur',
        );

        $katAction = new CreateKategoriAsetAction();
        $kategori = $katAction->execute($katDto);

        $this->assertInstanceOf(KategoriAset::class, $kategori);
        $this->assertEquals('MEB', $kategori->kode_kategori);

        $asetDto = new AsetBarangData(
            yayasanId: $yayasan->id,
            lembagaId: $lembaga->id,
            kategoriAsetId: $kategori->id,
            ruanganId: $ruangan->id,
            kodeInventaris: 'INV/2026/MEB/001',
            namaBarang: 'Meja Guru Kayu Jati',
            merk: 'Olympic',
            tipePencatatan: TipePencatatanAset::Unit,
            qty: 1,
            kondisi: KondisiAset::Baik,
            sumberPerolehan: SumberPerolehanAset::BeliLembaga,
            hargaPerolehan: 1500000.00
        );

        $asetAction = new CreateAsetBarangAction();
        $aset = $asetAction->execute($asetDto);

        $this->assertInstanceOf(AsetBarang::class, $aset);
        $this->assertEquals('Meja Guru Kayu Jati', $aset->nama_barang);
    }

    public function test_mutasi_aset_moves_location_and_creates_log(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan']);
        $lembaga = Lembaga::create(['yayasan_id' => $yayasan->id, 'nama' => 'SDIT', 'jenjang' => 'SD', 'npsn' => '123', 'status_aktif' => true]);
        $gedung = Gedung::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_gedung' => 'GD-1', 'nama_gedung' => 'Gedung 1']);
        $ruangAsal = Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'kode_ruangan' => 'R-1', 'nama_ruangan' => 'Ruang 1']);
        $ruangTujuan = Ruangan::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'gedung_id' => $gedung->id, 'kode_ruangan' => 'R-2', 'nama_ruangan' => 'Ruang 2']);
        $kategori = KategoriAset::create(['yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'kode_kategori' => 'ELK', 'nama_kategori' => 'Elektronik']);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $aset = AsetBarang::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kategori_aset_id' => $kategori->id,
            'ruangan_id' => $ruangAsal->id,
            'kode_inventaris' => 'INV/2026/ELK/001',
            'nama_barang' => 'Laptop Lenovo',
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'qty' => 1,
            'kondisi' => KondisiAset::Baik,
            'sumberPerolehan' => SumberPerolehanAset::BeliLembaga,
        ]);

        $mutasiDto = new MutasiAsetData(
            asetBarangId: $aset->id,
            ruanganTujuanId: $ruangTujuan->id,
            qtyPindah: 1,
            tanggalMutasi: now()->toDateString(),
            alasanMutasi: 'Kebutuhan Ruang 2',
            dilakukanOlehUserId: $user->id
        );

        $mutasiAction = new MutasiAsetRuanganAction();
        $log = $mutasiAction->execute($mutasiDto);

        $this->assertEquals($ruangTujuan->id, $aset->fresh()->ruangan_id);
        $this->assertEquals($ruangAsal->id, $log->ruangan_asal_id);
        $this->assertEquals($ruangTujuan->id, $log->ruangan_tujuan_id);
        $this->assertEquals($user->id, $log->dilakukan_oleh_user_id);
    }
}
