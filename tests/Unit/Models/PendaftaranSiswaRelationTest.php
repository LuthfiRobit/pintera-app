<?php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatPendaftaranAktifUntukSiswaTest(): Pendaftaran
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDays(10), 'tanggal_tutup' => now()->addDays(10), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $akun = AkunPendaftar::factory()->create();

    return Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id,
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999),
        'email_pendaftaran' => $akun->email,
        'status' => 'diterima',
        'submitted_at' => now(),
    ]);
}

it('exposes a siswa relation that resolves via pendaftaran_asal_id', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $pendaftaran->lembaga_id,
        'pendaftaran_asal_id' => $pendaftaran->id,
    ]);

    expect($pendaftaran->siswa->id)->toBe($siswa->id);
});

it('scopeSiapDidaftarkanSebagaiSiswa excludes pendaftaran that already have a siswa', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    Siswa::factory()->create(['lembaga_id' => $pendaftaran->lembaga_id, 'pendaftaran_asal_id' => $pendaftaran->id]);

    expect(Pendaftaran::siapDidaftarkanSebagaiSiswa()->whereKey($pendaftaran->id)->exists())->toBeFalse();
});

it('scopeSiapDidaftarkanSebagaiSiswa excludes pendaftaran that are not aktif', function () {
    $pendaftaran = buatPendaftaranAktifUntukSiswaTest();
    $pendaftaran->update(['status' => 'menunggu_verifikasi']);

    expect(Pendaftaran::siapDidaftarkanSebagaiSiswa()->whereKey($pendaftaran->id)->exists())->toBeFalse();
});
