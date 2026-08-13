<?php

use App\Contracts\PaymentGatewayInterface;
use App\Models\BriVirtualAccount;
use App\Models\BriQrisPayment;
use App\Models\JenisTagihan;
use App\Models\Pembayaran;
use App\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Models\Tagihan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

it('tops up the wallet remainder when a bundled VA payment is confirmed via webhook', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    $siswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'amount' => 150000, 'topup_status' => 'pending',
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    $va = BriVirtualAccount::create([
        'pembayaran_id' => $pembayaran->id, 'va_type' => 'BILL_DIRECT', 'va_number' => '8808081111111111',
        'amount' => 150000, 'expired_at' => now()->addHours(24), 'status' => 'WAITING',
    ]);
    $saldoAwal = (float) $siswa->wallet->balance;

    $response = $this->postJson('/webhook/bri/payment-notification', [
        'BrivaNo' => '880808', 'CustCode' => '1111111111', 'Status' => 'PAID', 'Amount' => 150000,
    ], [
        'BRI-Signature' => 'valid'
    ]);

    $response->assertOk();
    $tagihan->refresh();
    expect($tagihan->status)->toBe('lunas');
    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe($saldoAwal + 50000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});

it('does not attempt a topup for a plain (non-bundled) VA payment', function () {
    $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
    $mockGateway->shouldReceive('verifyCallbackSignature')->once()->andReturn(true);
    app()->instance(PaymentGatewayInterface::class, $mockGateway);

    $siswa = Siswa::factory()->create();
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);
    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'va_bri', 'status' => 'menunggu_pembayaran',
        'amount' => 100000, 'topup_status' => 'none', 'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => 100000]);
    BriVirtualAccount::create([
        'pembayaran_id' => $pembayaran->id, 'va_type' => 'BILL_DIRECT', 'va_number' => '8808082222222222',
        'amount' => 100000, 'expired_at' => now()->addHours(24), 'status' => 'WAITING',
    ]);
    $saldoAwal = (float) $siswa->wallet->balance;

    $response = $this->postJson('/webhook/bri/payment-notification', [
        'BrivaNo' => '880808', 'CustCode' => '2222222222', 'Status' => 'PAID', 'Amount' => 100000,
    ], [
        'BRI-Signature' => 'valid'
    ]);

    $response->assertOk();
    expect((float) $siswa->wallet->fresh()->balance)->toBe($saldoAwal);
    expect($pembayaran->fresh()->topup_status)->toBe('none');
});
