<?php

use App\Models\BriVirtualAccount;
use App\Models\Siswa;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the bri virtual accounts belonging to a wallet', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;

    $va = BriVirtualAccount::create([
        'wallet_id' => $wallet->id,
        'va_type' => 'WALLET_PERMANENT',
        'va_number' => '88081234567890',
        'status' => 'PERMANENT',
    ]);

    expect($wallet->briVirtualAccounts)->toHaveCount(1);
    expect($wallet->briVirtualAccounts->first()->id)->toBe($va->id);
});

it('returns an empty collection when the wallet has no virtual account yet', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;

    expect($wallet->briVirtualAccounts)->toHaveCount(0);
});
