<?php

use App\Models\Siswa;
use App\Models\SystemSetting;
use App\Domains\Keuangan\Models\Wallet;
use App\Domains\Keuangan\Models\WalletMutasi;
use App\Models\Lembaga;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a system setting and resolve relationships', function () {
    $lembaga = Lembaga::factory()->create();
    
    $setting = SystemSetting::create([
        'lembaga_id' => $lembaga->id,
        'key' => 'auto_debit_enabled',
        'value' => 'true',
        'description' => 'Test',
    ]);

    expect($setting->lembaga->id)->toBe($lembaga->id);
    expect($setting->key)->toBe('auto_debit_enabled');
});

it('can create a wallet and access relations', function () {
    $siswa = Siswa::factory()->create();
    
    $wallet = $siswa->wallet;
    $wallet->update([
        'balance' => 100000,
        'va_number' => 'VA-123456',
    ]);

    expect($wallet->siswa->id)->toBe($siswa->id);
    expect($siswa->wallet->id)->toBe($wallet->id);
    expect($wallet->balance)->toEqual(100000);
});

it('can create wallet mutasi', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 100000]);
    
    $mutasi = WalletMutasi::create([
        'wallet_id' => $wallet->id,
        'tipe' => 'topup',
        'amount' => 50000,
        'saldo_sebelum' => 50000,
        'saldo_sesudah' => 100000,
        'keterangan' => 'Test Topup',
    ]);

    expect($mutasi->wallet->id)->toBe($wallet->id);
    expect($wallet->mutasi)->toHaveCount(1);
    expect($mutasi->tipe)->toBe('topup');
});

it('resolves the pembayaran relation on wallet_mutasi correctly (regression: this relation was silently broken by an implicit same-namespace reference after Pembayaran moved domains in SP3)', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $pembayaran = \App\Domains\Keuangan\Models\Pembayaran::factory()->create();

    $mutasi = $wallet->mutasi()->create([
        'pembayaran_id' => $pembayaran->id,
        'tipe' => 'topup',
        'amount' => 100000,
        'saldo_sebelum' => 0,
        'saldo_sesudah' => 100000,
        'keterangan' => 'Test regresi relasi pembayaran',
    ]);

    expect($mutasi->pembayaran)->not->toBeNull();
    expect($mutasi->pembayaran->id)->toBe($pembayaran->id);
});
