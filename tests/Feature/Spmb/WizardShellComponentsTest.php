<?php
// tests/Feature/Spmb/WizardShellComponentsTest.php

use App\Models\AkunPendaftar;
use Illuminate\Support\Facades\Blade;

it('renders the authenticated navbar with the logged-in akun name, nav links, and a logout form', function () {
    $akun = AkunPendaftar::factory()->create(['nama' => 'Aditya Pratama']);
    $this->actingAs($akun, 'portal');

    $html = Blade::render('<x-portal-authenticated-navbar />');

    expect($html)->toContain('Aditya')
        ->toContain(route('portal.dashboard'))
        ->toContain(route('portal.logout'))
        ->toContain('Riwayat')
        ->toContain('Bantuan');
});

it('renders the wizard stepper with all 4 stages and marks the current one active', function () {
    $html = Blade::render('<x-portal-wizard-stepper current="dokumen" />');

    expect($html)->toContain('Data Diri')
        ->toContain('Formulir Tambahan')
        ->toContain('Dokumen')
        ->toContain('Review');
});

it('renders the wizard sidebar with the jalur name, lembaga name, and a pending-confirmation biaya when nominal is null', function () {
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $html = Blade::render(
        '<x-portal-wizard-sidebar :lembaga="$lembaga" :jalur="$jalur" :nominal="$nominal" />',
        ['lembaga' => $lembaga, 'jalur' => $jalur, 'nominal' => null]
    );

    expect($html)->toContain($jalur->nama)
        ->toContain($lembaga->nama)
        ->toContain('Menunggu Konfirmasi');
});

it('renders the wizard layout with the identity strip, stepper, and sidebar', function () {
    $akun = AkunPendaftar::factory()->create(['nama' => 'Aditya Pratama', 'email' => 'aditya@example.test']);
    $this->actingAs($akun, 'portal');
    [$lembaga, , $jalur] = buatLembagaDenganGelombangBuka();

    $html = Blade::render(
        '<x-layouts.portal-wizard title="Data Diri" current="data-diri" :lembaga="$lembaga" :jalur="$jalur" :nominal="null">Konten Uji</x-layouts.portal-wizard>',
        ['lembaga' => $lembaga, 'jalur' => $jalur]
    );

    expect($html)->toContain('Mendaftar sebagai')
        ->toContain('Aditya Pratama')
        ->toContain('aditya@example.test')
        ->toContain('Konten Uji')
        ->toContain($jalur->nama);
});
