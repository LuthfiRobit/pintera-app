<?php
// tests/Feature/Spmb/ReviewSubmitTest.php

use App\Models\AkunPendaftar;
use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function isiWizardLengkap($lembaga, $jalur): void
{
    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'nik' => '3201234567890123',
        'data_pribadi' => [
            'nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2015-03-10', 'agama' => 'Islam',
        ],
        'alamat' => [
            'alamat_jalan' => 'Jl. Merdeka 10', 'desa_kelurahan' => 'Sukamaju',
            'kecamatan' => 'Cibeunying', 'kabupaten_kota' => 'Bandung', 'provinsi' => 'Jawa Barat',
        ],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Budi Santoso']],
        'jawaban_formulir' => [],
        'dokumen' => [],
    ]);
}

it('shows a review summary of everything entered so far', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $this->get(route('portal.wizard.review'))
        ->assertOk()
        ->assertSee('Ahmad Fauzan');
});

it('submits the full pendaftaran atomically, links it directly to the logged-in akun, and clears the wizard session', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $pendaftaran = Pendaftaran::first();
    $response->assertRedirect(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]));

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->akun_pendaftar_id)->toBe($akun->id);
    expect($pendaftaran->email_pendaftaran)->toBe($akun->email);
    expect($pendaftaran->calonMurid->nama_lengkap)->toBe('Ahmad Fauzan');
    expect($pendaftaran->calonMurid->alamat->kabupaten_kota)->toBe('Bandung');
    expect($pendaftaran->calonMurid->keluarga)->toHaveCount(1);

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session)->toBe([]);
});

it('reuses the existing calon murid record when the nik already exists for this yayasan', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $existing = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    isiWizardLengkap($lembaga, $jalur);

    $this->post(route('portal.wizard.submit'));

    expect(CalonMurid::count())->toBe(1);
    expect(Pendaftaran::first()->calon_murid_id)->toBe($existing->id);
});

it('sends a confirmation email containing the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $this->post(route('portal.wizard.submit'));

    Mail::assertSent(App\Mail\PendaftaranBerhasilMail::class, function ($mail) use ($akun) {
        return $mail->hasTo($akun->email);
    });
});

it('retries with a fresh kode when the generated kode collides with a race-condition duplicate at insert time', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);

    $kodeGenerator = Mockery::mock(App\Services\KodePendaftaranGenerator::class);
    $kodeGenerator->shouldReceive('generate')->once()->andReturn('REG-2026-00001');
    $kodeGenerator->shouldReceive('generate')->once()->andReturn('REG-2026-00002');
    $this->app->instance(App\Services\KodePendaftaranGenerator::class, $kodeGenerator);

    $lain = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id]);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $lain->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'lain@example.test',
    ]);

    $response = $this->post(route('portal.wizard.submit'));

    $pendaftaran = Pendaftaran::where('kode_pendaftaran', 'REG-2026-00002')->first();
    expect($pendaftaran)->not->toBeNull();
    $response->assertRedirect(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]));
});

afterEach(function () {
    Mockery::close();
});

it('rolls back the whole submission and never moves the file when a document row fails to insert', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'nik' => '3201234567890123',
        'data_pribadi' => [
            'nama_lengkap' => 'Ahmad Fauzan', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2015-03-10', 'agama' => 'Islam',
        ],
        'alamat' => [
            'alamat_jalan' => 'Jl. Merdeka 10', 'desa_kelurahan' => 'Sukamaju',
            'kecamatan' => 'Cibeunying', 'kabupaten_kota' => 'Bandung', 'provinsi' => 'Jawa Barat',
        ],
        'keluarga' => [['jenis' => 'ayah', 'nama' => 'Budi Santoso']],
        'jawaban_formulir' => [],
        'dokumen' => [],
    ]);

    $tmpPath = 'pendaftaran-tmp/'.session()->getId().'/kartu-keluarga.pdf';
    Storage::disk('public')->put($tmpPath, 'isi dokumen palsu');

    $syaratIdTakDikenal = 999999;
    $wizardSession->put($lembaga, $jalur, [
        'dokumen' => [
            $syaratIdTakDikenal => [
                'file_path' => $tmpPath,
                'nama_file_asli' => 'kartu-keluarga.pdf',
                'mime_type' => 'application/pdf',
                'ukuran_bytes' => 18,
            ],
        ],
    ]);

    $this->post(route('portal.wizard.submit'));

    expect(Pendaftaran::count())->toBe(0);
    Storage::disk('public')->assertExists($tmpPath);
    expect(Storage::disk('public')->allFiles('pendaftaran'))->toBeEmpty();
});

it('redirects to review with a friendly message instead of a 500 when the calon murid already registered for this gelombang', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $akun = loginAkunDenganPilihanSpmb($lembaga, $jalur);
    $calonMuridLama = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMuridLama->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'akun_pendaftar_id' => $akun->id, 'email_pendaftaran' => $akun->email,
    ]);
    isiWizardLengkap($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $response->assertRedirect(route('portal.wizard.review'));
    $response->assertSessionHasErrors('submit');
    expect(Pendaftaran::count())->toBe(1);
});

it('redirects to data-diri instead of crashing when submit is hit with an incomplete session', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $response = $this->post(route('portal.wizard.submit'));

    $response->assertRedirect(route('portal.wizard.data-diri'));
    $response->assertSessionHasErrors('sesi');
    expect(Pendaftaran::count())->toBe(0);
});

it('redirects to the dashboard when review is visited with no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.review'))
        ->assertRedirect(route('portal.dashboard'));
});

it('404s on submit when the gelombang has closed since the wizard was started (regression, not a new fix)', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    \App\Models\GelombangPpdb::where('lembaga_id', $lembaga->id)->update([
        'tanggal_buka' => now()->subMonth(), 'tanggal_tutup' => now()->subDay(),
    ]);

    $this->post(route('portal.wizard.submit'))->assertNotFound();

    expect(Pendaftaran::count())->toBe(0);
});

it('shows the success page with the kode pendaftaran for the akun that owns it', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    $this->post(route('portal.wizard.submit'));
    $pendaftaran = Pendaftaran::first();

    $this->get(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]))
        ->assertOk()
        ->assertSee($pendaftaran->kode_pendaftaran);
});

it('404s the success page when a different akun tries to view it', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);
    isiWizardLengkap($lembaga, $jalur);
    $this->post(route('portal.wizard.submit'));
    $pendaftaran = Pendaftaran::first();

    $akunLain = AkunPendaftar::factory()->create();

    $this->actingAs($akunLain, 'portal')
        ->get(route('portal.wizard.berhasil', ['pendaftaran' => $pendaftaran]))
        ->assertNotFound();
});
