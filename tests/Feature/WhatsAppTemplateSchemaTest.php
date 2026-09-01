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

it('seeds all required template rows, including the 6 finance-module additions from Sub-project 05', function () {
    (new WhatsAppTemplateSeeder)->run();

    // Was originally "exactly 2" (consent_diminta, reminder_sesi_h1) before Sub-project 05
    // added 6 finance notification templates to the same seeder — count updated to 8, and
    // all 8 kode values are asserted individually so a future addition/removal is caught here too.
    expect(WhatsAppTemplate::count())->toBe(9);
    expect(WhatsAppTemplate::where('kode', 'consent_diminta')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'reminder_sesi_h1')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'tagihan_baru')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'pembayaran_berhasil')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'transfer_manual_disetujui')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'transfer_manual_ditolak')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'saldo_tidak_cukup')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'tagihan_jatuh_tempo')->exists())->toBeTrue();
    expect(WhatsAppTemplate::where('kode', 'tagihan_direvisi')->exists())->toBeTrue();
});
