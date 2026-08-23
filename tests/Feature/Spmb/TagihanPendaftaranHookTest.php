<?php
// tests/Feature/Spmb/TagihanPendaftaranHookTest.php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\NominalTagihanJalur;
use App\Models\Pendaftaran;
use App\Models\Tagihan;
use App\Services\PendaftaranWizardSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates a tagihan pendaftaran automatically when the wizard submit succeeds', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'Biaya Pendaftaran', 'kategori' => 'pendaftaran', 'bisa_dicicil' => false]);
    NominalTagihanJalur::create(['jenis_tagihan_id' => $jenisTagihan->id, 'jalur_ppdb_id' => $jalur->id, 'nominal' => 150000]);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'nik' => '3200000000000001',
        'data_pribadi' => ['nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Mawar', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Ahmad']],
    ]);

    $this->post(route('portal.wizard.submit'));

    $pendaftaran = Pendaftaran::first();
    expect($pendaftaran)->not->toBeNull();
    $tagihan = Tagihan::where('pendaftaran_id', $pendaftaran->id)->where('kategori', 'pendaftaran')->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total_tagihan)->toBe(150000.0);
});

it('still submits successfully even when no jenis tagihan is configured for the jalur', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    app(PendaftaranWizardSession::class)->put($lembaga, $jalur, [
        'nik' => '3200000000000002',
        'data_pribadi' => ['nama_lengkap' => 'Budi Santoso', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2012-01-01', 'agama' => 'Islam'],
        'alamat' => ['alamat_jalan' => 'Jl. Melati', 'desa_kelurahan' => 'A', 'kecamatan' => 'B', 'kabupaten_kota' => 'C', 'provinsi' => 'D'],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Bapak Budi']],
    ]);

    $response = $this->post(route('portal.wizard.submit'));

    $response->assertRedirect();
    $pendaftaran = Pendaftaran::first();
    expect($pendaftaran)->not->toBeNull();
    $this->assertDatabaseMissing('tagihan', ['pendaftaran_id' => $pendaftaran->id]);
});
