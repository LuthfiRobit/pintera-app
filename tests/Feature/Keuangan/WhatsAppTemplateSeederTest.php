<?php
// tests/Feature/Keuangan/WhatsAppTemplateSeederTest.php

use App\Models\WhatsAppTemplate;
use Database\Seeders\WhatsAppTemplateSeeder;

it('seeds all 6 finance whatsapp template kode values', function () {
    (new WhatsAppTemplateSeeder())->run();

    $kodes = ['tagihan_baru', 'pembayaran_berhasil', 'transfer_manual_disetujui', 'transfer_manual_ditolak', 'saldo_tidak_cukup', 'tagihan_jatuh_tempo'];

    foreach ($kodes as $kode) {
        expect(WhatsAppTemplate::where('kode', $kode)->exists())->toBeTrue("kode '{$kode}' should exist");
    }
});

it('renders tagihan_baru with the expected placeholders', function () {
    (new WhatsAppTemplateSeeder())->run();

    $rendered = WhatsAppTemplate::renderKode('tagihan_baru', [
        'jenis_tagihan' => 'SPP Bulanan', 'billing_period' => '2026-09', 'net_amount' => '500000', 'jatuh_tempo' => '10 Sep 2026',
    ]);

    expect($rendered)->toContain('SPP Bulanan');
    expect($rendered)->toContain('500000');
});
