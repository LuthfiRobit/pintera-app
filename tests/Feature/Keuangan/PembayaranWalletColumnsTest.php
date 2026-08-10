<?php
// tests/Feature/Keuangan/PembayaranWalletColumnsTest.php

use App\Models\Pembayaran;
use App\Models\Tagihan;

it('accepts the new metode and sumber values for siswa-facing payments', function () {
    $pembayaran = Pembayaran::create([
        'tagihan_id' => Tagihan::factory()->create()->id,
        'sumber' => 'orang_tua',
        'metode' => 'wallet_auto',
        'status' => 'lunas',
        'is_auto_allocation' => true,
    ]);

    expect($pembayaran->metode)->toBe('wallet_auto');
    expect($pembayaran->sumber)->toBe('orang_tua');
    expect($pembayaran->is_auto_allocation)->toBeTrue();
});

it('defaults identifier_method to manual and is_auto_allocation to false', function () {
    $pembayaran = Pembayaran::factory()->create();

    expect($pembayaran->identifier_method)->toBe('manual');
    expect($pembayaran->is_auto_allocation)->toBeFalse();
});
