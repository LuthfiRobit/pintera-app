<?php

use App\Models\CalonMurid;
use App\Models\Pendaftaran;
use App\Services\PendaftaranWizardSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function isiWizardLengkap($lembaga, $jalur, string $email): void
{
    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'email_pendaftaran' => $email,
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
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/review")
        ->assertOk()
        ->assertSee('Ahmad Fauzan');
});

it('submits the full pendaftaran atomically and clears the wizard session', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $pendaftaran = Pendaftaran::first();
    $response->assertRedirect(route('spmb.berhasil', [
        'lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $pendaftaran->kode_pendaftaran, 'email' => $pendaftaran->email_pendaftaran,
    ]));

    expect($pendaftaran->status)->toBe('menunggu_verifikasi');
    expect($pendaftaran->email_pendaftaran)->toBe('wali@example.test');
    expect($pendaftaran->calonMurid->nama_lengkap)->toBe('Ahmad Fauzan');
    expect($pendaftaran->calonMurid->alamat->kabupaten_kota)->toBe('Bandung');
    expect($pendaftaran->calonMurid->keluarga)->toHaveCount(1);

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session)->toBe([]);
});

it('reuses the existing calon murid record when the nik already exists for this yayasan', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $existing = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    expect(CalonMurid::count())->toBe(1);
    expect(Pendaftaran::first()->calon_murid_id)->toBe($existing->id);
});

it('sends a confirmation email containing the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    Mail::assertSent(App\Mail\PendaftaranBerhasilMail::class, function ($mail) {
        return $mail->hasTo('wali@example.test');
    });
});

it('retries with a fresh kode when the generated kode collides with a race-condition duplicate at insert time', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

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

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $response->assertRedirect(route('spmb.berhasil', [
        'lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => 'REG-2026-00002', 'email' => 'wali@example.test',
    ]));
    expect(Pendaftaran::where('kode_pendaftaran', 'REG-2026-00002')->exists())->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});

it('rolls back the whole submission and never moves the file when a document row fails to insert', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $wizardSession = new PendaftaranWizardSession();
    $wizardSession->put($lembaga, $jalur, [
        'email_pendaftaran' => 'wali@example.test',
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

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    expect(Pendaftaran::count())->toBe(0);
    Storage::disk('public')->assertExists($tmpPath);
    expect(Storage::disk('public')->allFiles('pendaftaran'))->toBeEmpty();
});

it('shows the success page with the kode pendaftaran', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");
    $kode = Pendaftaran::first()->kode_pendaftaran;

    $this->get(route('spmb.berhasil', [
        'lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $kode, 'email' => 'wali@example.test',
    ]))->assertOk()->assertSee($kode);
});

it('404s the success page when the email does not match', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');
    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");
    $kode = Pendaftaran::first()->kode_pendaftaran;

    $this->get(route('spmb.berhasil', [
        'lembagaSlug' => $lembaga->slug, 'kodePendaftaran' => $kode, 'email' => 'salah@example.test',
    ]))->assertNotFound();
});

it('redirects to review with a friendly message instead of a 500 when the calon murid already registered for this gelombang', function () {
    Mail::fake();
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur, $gelombang] = buatLembagaDenganGelombangBuka();
    $calonMuridLama = CalonMurid::factory()->create(['yayasan_id' => $lembaga->yayasan_id, 'nik' => '3201234567890123']);
    Pendaftaran::factory()->create([
        'calon_murid_id' => $calonMuridLama->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test',
    ]);
    isiWizardLengkap($lembaga, $jalur, 'wali@example.test');

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $response->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/review");
    $response->assertSessionHasErrors('submit');
    expect(Pendaftaran::count())->toBe(1);
});

it('redirects to data-diri instead of crashing when submit is hit with an incomplete session', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    (new PendaftaranWizardSession())->put($lembaga, $jalur, ['email_pendaftaran' => 'wali@example.test']);

    $response = $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/submit");

    $response->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/data-diri");
    $response->assertSessionHasErrors('sesi');
    expect(Pendaftaran::count())->toBe(0);
});

it('redirects to mulai instead of crashing when review is visited with no verified email in session', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();

    $response = $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/review");

    $response->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/mulai");
    $response->assertSessionHasErrors('sesi');
});
