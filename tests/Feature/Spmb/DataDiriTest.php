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
        'nama_ayah' => 'Budi Santoso',
        'nama_ibu' => 'Siti Aminah',
    ])->assertRedirect(route('portal.wizard.formulir-tambahan'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['data_pribadi']['nama_lengkap'])->toBe('Ahmad Fauzan');
    expect($session['keluarga'])->toHaveCount(2);
    expect($session['keluarga'][0])->toBe(['jenis' => 'ayah', 'nama' => 'Budi Santoso', 'pekerjaan' => null]);
    expect($session['keluarga'][1])->toBe(['jenis' => 'ibu', 'nama' => 'Siti Aminah', 'pekerjaan' => null]);
});

function payloadDataDiriValid(array $override = []): array
{
    return array_merge([
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
        'nama_ayah' => 'Budi Santoso',
        'nama_ibu' => 'Siti Aminah',
    ], $override);
}

it('rejects a nisn that is not exactly 10 digits', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['nisn' => '12345']))
        ->assertSessionHasErrors('nisn');
});

it('rejects a kode pos that is not exactly 5 digits', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['kode_pos' => '123']))
        ->assertSessionHasErrors('kode_pos');
});

it('rejects a non-numeric rt or rw', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['rt' => 'abc']))
        ->assertSessionHasErrors('rt');
});

it('rejects a golongan darah outside A, B, AB, O', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['golongan_darah' => 'Z']))
        ->assertSessionHasErrors('golongan_darah');
});

it('rejects a tanggal lahir in the future', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['tanggal_lahir' => now()->addYear()->toDateString()]))
        ->assertSessionHasErrors('tanggal_lahir');
});

it('rejects a pekerjaan value outside the curated list', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['pekerjaan_ayah' => 'Astronot']))
        ->assertSessionHasErrors('pekerjaan_ayah');
});

it('rejects a no telepon containing letters', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), payloadDataDiriValid(['no_telepon' => 'bukan-nomor-telepon']))
        ->assertSessionHasErrors('no_telepon');
});

it('includes wali in the stored keluarga list only when nama_wali is filled in', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.data-diri.store'), [
        'nik' => '3201234567890124',
        'nama_lengkap' => 'Citra Lestari',
        'jenis_kelamin' => 'P',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2015-03-10',
        'agama' => 'Islam',
        'alamat_jalan' => 'Jl. Merdeka 10',
        'desa_kelurahan' => 'Sukamaju',
        'kecamatan' => 'Cibeunying',
        'kabupaten_kota' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'nama_ayah' => 'Budi Santoso',
        'nama_ibu' => 'Siti Aminah',
        'nama_wali' => 'Rina Wijaya',
        'pekerjaan_wali' => 'Guru/Dosen',
    ])->assertRedirect(route('portal.wizard.formulir-tambahan'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['keluarga'])->toHaveCount(3);
    expect($session['keluarga'][2])->toBe(['jenis' => 'wali', 'nama' => 'Rina Wijaya', 'pekerjaan' => 'Guru/Dosen']);
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
        'nama_ayah' => 'Percobaan',
        'nama_ibu' => 'Percobaan',
    ]);

    $response->assertSessionHasErrors('nik');

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['nik'] ?? null)->toBeNull();
    $calonMurid->refresh();
    expect($calonMurid->nama_lengkap)->toBe('Nama Lama');
});
