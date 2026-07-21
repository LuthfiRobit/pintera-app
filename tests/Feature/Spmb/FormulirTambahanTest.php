<?php
// tests/Feature/Spmb/FormulirTambahanTest.php

use App\Models\AkunPendaftar;
use App\Models\FormulirField;
use App\Services\PendaftaranWizardSession;

it('shows dynamic formulir fields for the selected jalur', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();
    FormulirField::create(['jalur_ppdb_id' => $jalur->id, 'label' => 'Nilai Rata-rata Rapor', 'field_type' => 'number']);
    loginAkunDenganPilihanSpmb($lembaga, $jalur);

    $this->get(route('portal.wizard.formulir-tambahan'))
        ->assertOk()
        ->assertSee('Nilai Rata-rata Rapor');
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

it('redirects to the dashboard when there is no jalur selected in session', function () {
    $akun = AkunPendaftar::factory()->create();

    $this->actingAs($akun, 'portal')->get(route('portal.wizard.formulir-tambahan'))
        ->assertRedirect(route('portal.dashboard'));
});
