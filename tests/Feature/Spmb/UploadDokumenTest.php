<?php

use App\Models\AkunPendaftar;
use App\Models\DokumenSyaratPpdb;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows the dokumen syarat list for the selected jalur', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.dokumen'))
        ->assertOk()
        ->assertSee('Akta Kelahiran');
});

it('uploads a valid file and stores its temp path in the wizard session', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $file = UploadedFile::fake()->create('akta.pdf', 500, 'application/pdf');

    $this->post(route('portal.wizard.dokumen.store'), [
        'dokumen' => [$syarat->id => $file],
    ])->assertRedirect(route('portal.wizard.review'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['dokumen'][$syarat->id]['nama_file_asli'])->toBe('akta.pdf');
    Storage::disk('public')->assertExists($session['dokumen'][$syarat->id]['file_path']);
});

it('rejects a file that is too large or the wrong type', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $tooBig = UploadedFile::fake()->create('akta.pdf', 3000, 'application/pdf');

    $this->post(route('portal.wizard.dokumen.store'), [
        'dokumen' => [$syarat->id => $tooBig],
    ])->assertSessionHasErrors("dokumen.{$syarat->id}");
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.dokumen'))
        ->assertRedirect(route('portal.dashboard'));
});
