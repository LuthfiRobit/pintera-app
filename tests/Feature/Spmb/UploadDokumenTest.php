<?php

use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the dokumen syarat list for the selected jalur', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen")
        ->assertOk()
        ->assertSee('Akta Kelahiran');
});

it('uploads a valid file and stores its temp path in the wizard session', function () {
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $file = UploadedFile::fake()->create('akta.pdf', 500, 'application/pdf');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen", [
        'dokumen' => [$syarat->id => $file],
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/review");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['dokumen'][$syarat->id]['nama_file_asli'])->toBe('akta.pdf');
    Storage::disk('public')->assertExists($session['dokumen'][$syarat->id]['file_path']);
});

it('rejects a file that is too large or the wrong type', function () {
    Storage::fake('public');
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $tooBig = UploadedFile::fake()->create('akta.pdf', 3000, 'application/pdf');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen", [
        'dokumen' => [$syarat->id => $tooBig],
    ])->assertSessionHasErrors("dokumen.{$syarat->id}");
});
