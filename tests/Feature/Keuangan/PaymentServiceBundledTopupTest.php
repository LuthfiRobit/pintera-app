<?php

use App\Models\JenisTagihan;
use App\Models\Lembaga;
use App\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\Tagihan;
use App\Models\Yayasan;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupSiswaDanTagihanUntukTopup(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'belum_bayar', 'net_amount' => 100000, 'paid_amount' => 0,
    ]);

    return [$siswa, collect([$tagihan])];
}

it('creates a bundled VA payment by summing tagihan and topup amounts', function () {
    config(['services.bri.gateway' => 'mock']);
    [$siswa, $tagihans] = setupSiswaDanTagihanUntukTopup();

    $pembayaran = app(PaymentService::class)->createVaPaymentWithTopup($siswa, $tagihans, 50000);

    expect((float) $pembayaran->amount)->toBe(150000.0);
    expect($pembayaran->topup_status)->toBe('pending');
    expect($pembayaran->briVirtualAccount)->not->toBeNull();
});

it('creates a bundled QRIS payment by summing tagihan and topup amounts', function () {
    config(['services.bri.gateway' => 'mock']);
    [$siswa, $tagihans] = setupSiswaDanTagihanUntukTopup();

    $pembayaran = app(PaymentService::class)->createQrisPaymentWithTopup($siswa, $tagihans, 20000);

    expect((float) $pembayaran->amount)->toBe(120000.0);
    expect($pembayaran->topup_status)->toBe('pending');
    expect($pembayaran->briQrisPayment)->not->toBeNull();
});

it('rejects a bundled VA request with zero or negative topup amount', function () {
    config(['services.bri.gateway' => 'mock']);
    [$siswa, $tagihans] = setupSiswaDanTagihanUntukTopup();

    $this->expectException(\App\Exceptions\PaymentException::class);

    app(PaymentService::class)->createVaPaymentWithTopup($siswa, $tagihans, 0.0);
});

it('rejects a bundled VA request with no tagihan selected', function () {
    config(['services.bri.gateway' => 'mock']);
    [$siswa] = setupSiswaDanTagihanUntukTopup();

    $this->expectException(\App\Exceptions\PaymentException::class);

    app(PaymentService::class)->createVaPaymentWithTopup($siswa, collect(), 50000.0);
});
