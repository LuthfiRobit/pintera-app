<?php

use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Wallet;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Services\Finance\AutoAllocationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allocates wallet balance to tagihan fully when balance is sufficient', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 500000]);

    $jenis1 = JenisTagihan::factory()->create(['priority_score' => 1]);
    $jenis2 = JenisTagihan::factory()->create(['priority_score' => 2]);

    $tagihan1 = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis1->id,
        'total_tagihan' => 200000,
        'net_amount' => 200000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'jatuh_tempo' => now()->addDays(5),
    ]);

    $tagihan2 = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis2->id,
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'jatuh_tempo' => now()->addDays(10),
    ]);

    $engine = app(AutoAllocationEngine::class);
    $engine->run($wallet);

    $wallet->refresh();
    $tagihan1->refresh();
    $tagihan2->refresh();

    expect($wallet->balance)->toEqual(150000);
    expect($tagihan1->status)->toBe('lunas');
    expect($tagihan1->paid_amount)->toEqual(200000);
    expect($tagihan2->status)->toBe('lunas');
    expect($tagihan2->paid_amount)->toEqual(150000);

    $pembayaran = Pembayaran::first();
    expect($pembayaran->metode)->toBe('wallet_auto');
    expect($pembayaran->pembayaranTagihan->sum('amount_allocated'))->toEqual(350000);
    expect($pembayaran->pembayaranTagihan)->toHaveCount(2);
});

it('allocates wallet balance partially top-down', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 300000]);

    $jenis1 = JenisTagihan::factory()->create(['priority_score' => 1]);
    $jenis2 = JenisTagihan::factory()->create(['priority_score' => 2]);
    $jenis3 = JenisTagihan::factory()->create(['priority_score' => 3]);

    $tagihan1 = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis1->id,
        'total_tagihan' => 200000,
        'net_amount' => 200000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
    ]);

    $tagihan2 = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis2->id,
        'total_tagihan' => 150000,
        'net_amount' => 150000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
    ]);

    $tagihan3 = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis3->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
    ]);

    $engine = app(AutoAllocationEngine::class);
    $engine->run($wallet);

    $wallet->refresh();
    $tagihan1->refresh();
    $tagihan2->refresh();
    $tagihan3->refresh();

    expect($wallet->balance)->toEqual(0);
    
    // Prioritas 1 lunas (200.000)
    expect($tagihan1->status)->toBe('lunas');
    expect($tagihan1->paid_amount)->toEqual(200000);

    // Prioritas 2 sebagian (sisa saldo 100.000)
    expect($tagihan2->status)->toBe('sebagian');
    expect($tagihan2->paid_amount)->toEqual(100000);

    // Prioritas 3 belum dibayar (0)
    expect($tagihan3->status)->toBe('belum_bayar');
    expect($tagihan3->paid_amount)->toEqual(0);
});

it('resolves tie-breaker by jatuh_tempo', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 100000]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 5]);

    $tagihanBaru = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'jatuh_tempo' => now()->addDays(5),
    ]);

    $tagihanLama = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'belum_bayar',
        'jatuh_tempo' => now()->subDays(5),
    ]);

    $engine = app(AutoAllocationEngine::class);
    $engine->run($wallet);

    $tagihanBaru->refresh();
    $tagihanLama->refresh();

    // Saldo 100.000, diprioritaskan untuk tagihanLama
    expect($tagihanLama->status)->toBe('lunas');
    expect($tagihanLama->paid_amount)->toEqual(100000);

    expect($tagihanBaru->status)->toBe('belum_bayar');
    expect($tagihanBaru->paid_amount)->toEqual(0);
});

it('does not allocate to dibatalkan tagihan', function () {
    $siswa = Siswa::factory()->create();
    $wallet = $siswa->wallet;
    $wallet->update(['balance' => 100000]);

    $jenis = JenisTagihan::factory()->create(['priority_score' => 1]);

    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id,
        'tagihable_type' => Siswa::class,
        'jenis_tagihan_id' => $jenis->id,
        'total_tagihan' => 100000,
        'net_amount' => 100000,
        'paid_amount' => 0,
        'status' => 'dibatalkan',
    ]);

    $engine = app(AutoAllocationEngine::class);
    $engine->run($wallet);

    $tagihan->refresh();
    $wallet->refresh();

    expect($tagihan->paid_amount)->toEqual(0);
    expect($wallet->balance)->toEqual(100000); // Saldo utuh
});
