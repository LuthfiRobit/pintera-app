<?php

use App\Models\WhatsAppTemplate;
use Database\Seeders\WhatsAppTemplateSeeder;

it('renders a template with placeholders replaced', function () {
    WhatsAppTemplate::create([
        'kode' => 'contoh_kode',
        'isi_template' => 'Halo {nama_siswa}, sesi pada {tanggal_sesi}.',
    ]);

    $rendered = WhatsAppTemplate::renderKode('contoh_kode', [
        'nama_siswa' => 'Budi',
        'tanggal_sesi' => '10 Agustus 2026',
    ]);

    expect($rendered)->toBe('Halo Budi, sesi pada 10 Agustus 2026.');
});

it('leaves an unrecognized placeholder untouched in the rendered message', function () {
    WhatsAppTemplate::create([
        'kode' => 'contoh_typo',
        'isi_template' => 'Halo {nama_siwa}.',
    ]);

    $rendered = WhatsAppTemplate::renderKode('contoh_typo', ['nama_siswa' => 'Budi']);

    expect($rendered)->toBe('Halo {nama_siwa}.');
});

it('returns null when rendering a kode that has no template row', function () {
    expect(WhatsAppTemplate::renderKode('kode_tidak_ada', []))->toBeNull();
});

it('seeds exactly the two required template rows', function () {
    (new WhatsAppTemplateSeeder())->run();

    expect(WhatsAppTemplate::count())->toBe(2);
    expect(WhatsAppTemplate::where('kode', 'consent_diminta')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'reminder_sesi_h1')->exists())->toBeTrue();
});
