<?php

use App\Models\JenisTagihan;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('retries a failed bundled topup using the remainder, not the full amount (regression: no double-count)', function () {
    $siswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => 100000, 'paid_amount' => 100000,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'qris', 'status' => 'lunas',
        'amount' => 150000, 'topup_status' => 'failed',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $saldoAwal = (float) $siswa->wallet->balance;

    $this->artisan('finance:reconcile-payments')->assertExitCode(0);

    $siswa->wallet->refresh();
    // MUST be +50000 (the remainder after the tagihan's 100000 was already
    // allocated), never +150000 (the full pembayaran amount) -- that would
    // silently double-credit the wallet for money that already paid the bill.
    expect((float) $siswa->wallet->balance)->toBe($saldoAwal + 50000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});

it('still retries a pure (non-bundled) failed topup for the full amount', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'transfer_manual', 'status' => 'lunas',
        'amount' => 75000, 'topup_status' => 'failed',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    $saldoAwal = (float) $siswa->wallet->balance;

    $this->artisan('finance:reconcile-payments')->assertExitCode(0);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe($saldoAwal + 75000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});
