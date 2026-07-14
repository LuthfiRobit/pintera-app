<?php

use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;

it('shows dynamic formulir fields for the selected jalur', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan")
        ->assertOk()
        ->assertSee('Nilai Rata-rata Rapor');
});

it('skips straight through when the jalur has no dynamic formulir fields', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->get("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan")->assertOk();
});

it('stores jawaban in the wizard session and advances to upload dokumen', function () {
    [$lembaga, $tahunAjaran, $jalur] = buatLembagaDenganGelombangBuka();
    $field = FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    siapkanEmailTerverifikasi($lembaga, $jalur, 'wali@example.test');

    $this->post("/spmb/{$lembaga->slug}/{$jalur->id}/formulir-tambahan", [
        'jawaban' => [$field->id => '88.5'],
    ])->assertRedirect("/spmb/{$lembaga->slug}/{$jalur->id}/dokumen");

    $session = (new PendaftaranWizardSession())->get($lembaga, $jalur);
    expect($session['jawaban_formulir'][$field->id])->toBe('88.5');
});
