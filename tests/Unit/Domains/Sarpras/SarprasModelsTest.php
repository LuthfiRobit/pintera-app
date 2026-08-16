<?php

namespace Tests\Unit\Domains\Sarpras;

use App\Domains\Sarpras\Enums\JenisRuangan;
use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Domains\Sarpras\Models\AsetBarang;
use App\Domains\Sarpras\Models\Gedung;
use App\Domains\Sarpras\Models\KategoriAset;
use App\Domains\Sarpras\Models\RiwayatMutasiAset;
use App\Domains\Sarpras\Models\Ruangan;
use App\Models\Guru;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\PolaJam;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SarprasModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gedung_and_ruangan_hierarchy(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SDIT Al-Hikmah',
            'jenjang' => 'SD',
            'npsn' => '12345678',
            'status_aktif' => true,
        ]);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-A',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 3,
            'is_aktif' => true,
        ]);

        $ruangan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-101',
            'nama_ruangan' => 'Ruang 101 Teori',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
            'kapasitas_siswa' => 36,
            'is_shared' => false,
            'is_aktif' => true,
        ]);

        $this->assertCount(1, $gedung->ruangan);
        $this->assertEquals('Gedung Utama', $ruangan->gedung->nama_gedung);
        $this->assertEquals(JenisRuangan::KelasTeori, $ruangan->jenis_ruangan);
    }

    public function test_kategori_and_aset_barang_with_mutasi(): void
    {
        $yayasan = Yayasan::create(['nama' => 'Yayasan Pendidikan']);
        $lembaga = Lembaga::create([
            'yayasan_id' => $yayasan->id,
            'nama' => 'SDIT Al-Hikmah',
            'jenjang' => 'SD',
            'npsn' => '12345678',
            'status_aktif' => true,
        ]);

        $gedung = Gedung::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_gedung' => 'GD-A',
            'nama_gedung' => 'Gedung Utama',
            'jumlah_lantai' => 3,
            'is_aktif' => true,
        ]);

        $ruangAsal = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-101',
            'nama_ruangan' => 'Ruang 101',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
        ]);

        $ruangTujuan = Ruangan::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'gedung_id' => $gedung->id,
            'kode_ruangan' => 'R-102',
            'nama_ruangan' => 'Ruang 102',
            'lantai' => 1,
            'jenis_ruangan' => JenisRuangan::KelasTeori,
        ]);

        $kategori = KategoriAset::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kode_kategori' => 'ELK',
            'nama_kategori' => 'Elektronik & IT',
        ]);

        $aset = AsetBarang::create([
            'yayasan_id' => $yayasan->id,
            'lembaga_id' => $lembaga->id,
            'kategori_aset_id' => $kategori->id,
            'ruangan_id' => $ruangAsal->id,
            'kode_inventaris' => 'INV/SD/2026/ELK/001',
            'nama_barang' => 'Proyektor Epson',
            'tipe_pencatatan' => TipePencatatanAset::Unit,
            'qty' => 1,
            'satuan' => 'unit',
            'kondisi' => KondisiAset::Baik,
            'sumber_perolehan' => SumberPerolehanAset::BeliLembaga,
        ]);

        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

        $mutasi = RiwayatMutasiAset::create([
            'aset_barang_id' => $aset->id,
            'ruangan_asal_id' => $ruangAsal->id,
            'ruangan_tujuan_id' => $ruangTujuan->id,
            'qty_pindah' => 1,
            'tanggal_mutasi' => now()->toDateString(),
            'alasan_mutasi' => 'Pindah kelas untuk presentasi',
            'dilakukan_oleh_user_id' => $user->id,
        ]);

        $this->assertEquals('Elektronik & IT', $aset->kategori->nama_kategori);
        $this->assertEquals('Ruang 101', $aset->ruangan->nama_ruangan);
        $this->assertCount(1, $aset->riwayatMutasi);
        $this->assertEquals('Ruang 102', $mutasi->ruanganTujuan->nama_ruangan);
    }
}
