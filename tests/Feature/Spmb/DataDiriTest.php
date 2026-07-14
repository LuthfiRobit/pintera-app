<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;

it('shows the data diri form for a new nik', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri")->assertOk();
});

it('stores data diri in the wizard session and advances to formulir tambahan', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri", [
        'nik' => '3201234567890123',
        'nama_lengkap' => 'Ahmad Fauzan',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'alamat_jalan' => 'Jl. Merdeka 10',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'keluarga' => [
            ['jenis' => 'ayah', 'nama' => 'Budi Santoso'],
            ['jenis' => 'ibu', 'nama' => 'Siti Aminah'],
        ],
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['data_pribadi']['nama_lengkap'])->toBe('Ahmad Fauzan');
    expect($session['keluarga'])->toHaveCount(2);
});

it('pre-fills data diri from an existing calon murid when nik and email both match a prior pendaftaran', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id,
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'email_pendaftaran' => 'wali-lama@example.test',
    ]);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali-lama@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri/cek-nik", ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('blocks the flow when nik matches but email does not match any prior pendaftaran for that calon murid', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id,
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'email_pendaftaran' => 'wali-asli@example.test',
    ]);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali-beda@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri/cek-nik", ['nik' => '3201234567890999']);

    $response->assertStatus(422);
    $response->assertDontSee('Nama Lama');
});
