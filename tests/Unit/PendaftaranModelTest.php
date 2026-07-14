<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatKonteksPendaftaran(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);

    return [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid];
}

it('creates a pendaftaran linking calon murid to lembaga, tahun ajaran, jalur, and gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();

    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id,
        'lembaga_id' => $lembaga->id,
        'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id,
        'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001',
        'email_pendaftaran' => 'wali@example.test',
        'submitted_at' => now(),
    ]);

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->calonMurid->id)->toBe($calonMurid->id);
    expect($pendaftaran->lembaga->id)->toBe($lembaga->id);
    expect($pendaftaran->jalurPpdb->id)->toBe($jalur->id);
    expect($pendaftaran->gelombangPpdb->id)->toBe($gelombang->id);
});

it('rejects a second pendaftaran for the same calon murid in the same gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect(fn () => Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00002', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows the same calon murid to register again in a different gelombang', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();
    $gelombangKedua = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 2',
        'tanggal_buka' => now()->addMonth(), 'tanggal_tutup' => now()->addMonths(2), 'kuota' => 40,
    ]);

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    $kedua = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombangKedua->id,
        'kode_pendaftaran' => 'REG-2026-00002', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect($kedua->id)->not->toBe(null);
    expect(Pendaftaran::where('calon_murid_id', $calonMurid->id)->count())->toBe(2);
});

it('rejects a duplicate kode_pendaftaran', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang, $calonMurid] = buatKonteksPendaftaran();
    $calonMuridLain = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);

    Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);

    expect(fn () => Pendaftaran::create([
        'calon_murid_id' => $calonMuridLain->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'lain@example.test', 'submitted_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows two different lembaga to independently use the same kode_pendaftaran, since numbering restarts per lembaga', function () {
    [$lembagaA, $tahunAjaranA, $jalurA, $gelombangA, $calonMuridA] = buatKonteksPendaftaran();
    [$lembagaB, $tahunAjaranB, $jalurB, $gelombangB, $calonMuridB] = buatKonteksPendaftaran();

    $pendaftaranA = Pendaftaran::create([
        'calon_murid_id' => $calonMuridA->id, 'lembaga_id' => $lembagaA->id, 'tahun_ajaran_id' => $tahunAjaranA->id,
        'jalur_ppdb_id' => $jalurA->id, 'gelombang_ppdb_id' => $gelombangA->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali-a@example.test', 'submitted_at' => now(),
    ]);

    $pendaftaranB = Pendaftaran::create([
        'calon_murid_id' => $calonMuridB->id, 'lembaga_id' => $lembagaB->id, 'tahun_ajaran_id' => $tahunAjaranB->id,
        'jalur_ppdb_id' => $jalurB->id, 'gelombang_ppdb_id' => $gelombangB->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali-b@example.test', 'submitted_at' => now(),
    ]);

    expect($pendaftaranA->id)->not->toBe($pendaftaranB->id);
    expect($pendaftaranA->kode_pendaftaran)->toBe($pendaftaranB->kode_pendaftaran);
});
