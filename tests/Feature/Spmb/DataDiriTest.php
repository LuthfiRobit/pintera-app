<?php
// tests/Feature/Spmb/DataDiriTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;

it('shows the data diri form for a logged-in akun with a jalur in session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.data-diri'))->assertOk();
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.data-diri'))
        ->assertRedirect(route('portal.dashboard'));
});

it('stores data diri in the wizard session and advances to formulir tambahan', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), [
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
    ])->assertRedirect(route('portal.wizard.formulir-tambahan'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['data_pribadi']['nama_lengkap'])->toBe('Ahmad Fauzan');
    expect($session['keluarga'])->toHaveCount(2);
});

it('pre-fills data diri from an existing calon murid with no prior pendaftaran at all', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('pre-fills data diri when nik matches a calon murid whose prior pendaftaran belongs to the same akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akun->id, 'email_pendaftaran' => $akun->email,
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertOk();
    $response->assertSee('Nama Lama');
});

it('blocks the flow when nik matches a calon murid whose prior pendaftaran belongs to a different akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $akunLain = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akunLain->id, 'email_pendaftaran' => $akunLain->email,
    ]);

    $response = $this->postJson(route('portal.wizard.data-diri.cek-nik'), ['nik' => '3201234567890999']);

    $response->assertStatus(422);
    $response->assertDontSee('Nama Lama');
});

it('blocks store() from writing to the session when nik matches a calon murid owned by a different akun', function () {
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $akunLain = AkunPendaftar::factory()->create();
    $calonMurid = CalonMurid::factory()->create([
        'yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890999', 'nama_lengkap' => 'Nama Lama',
    ]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'akun_pendaftar_id' => $akunLain->id, 'email_pendaftaran' => $akunLain->email,
    ]);

    $response = $this->post(route('portal.wizard.data-diri.store'), [
        'nik' => '3201234567890999',
        'nama_lengkap' => 'Percobaan Curi Data',
        'jenis_kelamin' => 'L',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'alamat_jalan' => 'Jl. Merdeka 10',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Percobaan']],
    ]);

    $response->assertSessionHasErrors('nik');

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['nik'] ?? null)->toBeNull();
    $calonMurid->refresh();
    expect($calonMurid->nama_lengkap)->toBe('Nama Lama');
});
