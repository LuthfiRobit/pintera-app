<?php
// tests/Feature/Spmb/FormulirTambahanTest.php

use App\Models\AkunPendaftar;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows dynamic formulir fields for the selected jalur', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))
        ->assertOk()
        ->assertSee('Nilai Rata-rata Rapor');
});

it('pairs two consecutive compact fields into a 2-column row', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Sekolah Asal', 'field_type' => 'text', 'urutan' => 0]);
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'urutan' => 1]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))
        ->assertOk()
        ->assertSee('grid-cols-2', false);
});

it('gives a select field past the searchable threshold its own full-width row without native select classes', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create([
        'jalur_ppdb_id' => $jalur->id, 'label' => 'Provinsi Asal Sekolah', 'field_type' => 'select', 'urutan' => 0,
        'options' => collect(range(1, 15))->map(fn ($n) => "Opsi {$n}")->all(),
    ]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $response = $this->get(route('portal.wizard.formulir-tambahan'))->assertOk();

    // The Tom Select-enhanced <select> must carry no styling classes of its own —
    // Tom Select copies whatever class attribute it finds onto the wrapper it builds,
    // which would double up with the .ts-control theming already applied via CSS.
    $response->assertDontSee('field-select-chevron', false);
});

it('skips straight through when the jalur has no dynamic formulir fields', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))->assertOk();
});

it('stores jawaban in the wizard session and advances to upload dokumen', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => '88.5'],
    ])->assertRedirect(route('portal.wizard.dokumen'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['jawaban_formulir'][$field->id])->toBe('88.5');
});

it('rejects a non-numeric value for a number field', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number', 'is_required' => true]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => 'bukan angka'],
    ])->assertSessionHasErrors("jawaban.{$field->id}");
});

it('rejects an invalid date for a date field', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Tanggal Tes', 'field_type' => 'date', 'is_required' => true]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => 'bukan tanggal'],
    ])->assertSessionHasErrors("jawaban.{$field->id}");
});

it('rejects a select value outside the field own options', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create([
        'jalur_ppdb_id' => $jalur->id, 'label' => 'Jenis Prestasi', 'field_type' => 'select',
        'options' => ['Akademik', 'Non-Akademik'], 'is_required' => true,
    ]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => 'Opsi Tidak Ada'],
    ])->assertSessionHasErrors("jawaban.{$field->id}");
});

it('rejects a file with the wrong mime type for a file field', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Sertifikat', 'field_type' => 'file', 'is_required' => true]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => UploadedFile::fake()->create('sertifikat.exe', 100, 'application/x-msdownload')],
    ])->assertSessionHasErrors("jawaban.{$field->id}");
});

it('uploads a file answer and stores its metadata in the wizard session', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Sertifikat', 'field_type' => 'file', 'is_required' => true]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $file = UploadedFile::fake()->create('sertifikat.pdf', 200, 'application/pdf');

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$field->id => $file],
    ])->assertRedirect(route('portal.wizard.dokumen'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    $jawaban = $session['jawaban_formulir'][$field->id];
    expect($jawaban['nama_file_asli'])->toBe('sertifikat.pdf');
    expect($jawaban['mime_type'])->toBe('application/pdf');
    Storage::disk('public')->assertExists($jawaban['file_path']);
});

it('preserves a previously uploaded optional file answer when resubmitted without a new file', function () {
    Storage::fake('public');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    $fieldFile = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Sertifikat', 'field_type' => 'file', 'is_required' => false]);
    $fieldNumber = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai', 'field_type' => 'number', 'is_required' => true]);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $file = UploadedFile::fake()->create('sertifikat.pdf', 200, 'application/pdf');
    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$fieldFile->id => $file, $fieldNumber->id => '80'],
    ])->assertRedirect(route('portal.wizard.dokumen'));

    $pathPertama = (new PendaftaranWizardSession())->get($lembaga, $jalur)['jawaban_formulir'][$fieldFile->id]['file_path'];

    $this->post(route('portal.wizard.formulir-tambahan.store'), [
        'jawaban' => [$fieldNumber->id => '90'],
    ])->assertRedirect(route('portal.wizard.dokumen'));

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['jawaban_formulir'][$fieldFile->id]['file_path'])->toBe($pathPertama);
    expect($session['jawaban_formulir'][$fieldNumber->id])->toBe('90');
});

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.formulir-tambahan'))
        ->assertRedirect(route('portal.dashboard'));
});
