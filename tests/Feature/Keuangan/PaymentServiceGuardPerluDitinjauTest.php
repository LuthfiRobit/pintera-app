<?php

use App\Domains\Keuangan\Contracts\PaymentGatewayInterface;
use App\Domains\Keuangan\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentService;
use App\Exceptions\PaymentException;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.bri.gateway' => 'mock']);
    $this->app->forgetInstance(PaymentGatewayInterface::class);
});

it('rejects wallet payment for a tagihan flagged perlu_ditinjau_ulang', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 100000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    expect(fn () => app(PaymentService::class)->createWalletPayment($siswa, collect([$tagihan])))
        ->toThrow(PaymentException::class);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe(100000.0);
});

it('rejects manual transfer payment for a tagihan flagged perlu_ditinjau_ulang', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
        'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    expect(fn () => app(PaymentService::class)->createManualPayment($siswa, collect([$tagihan]), [
        'amount' => 60000, 'transfer_proof_path' => 'bukti-transfer/contoh.jpg',
        'bank_origin' => 'BCA', 'transfer_date' => now()->toDateString(), 'requested_by' => null,
    ]))->toThrow(PaymentException::class);

    expect(ManualPaymentRequest::count())->toBe(0);
});

it('rejects a race where the tagihan gets flagged after checkout page load but before wallet-payment commit', function () {
    $siswa = Siswa::factory()->create();
    $siswa->wallet->update(['balance' => 100000]);
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 60000, 'net_amount' => 60000, 'paid_amount' => 0,
    ]);

    // Simulasikan CheckoutController::create() memuat koleksi tagihan (belum di-flag),
    // lalu SEBELUM parent submit, admin memicu recalc yang men-flag tagihan ini.
    $staleTagihans = collect([$tagihan]);
    $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh']);

    expect(fn () => app(PaymentService::class)->createWalletPayment($siswa, $staleTagihans))
        ->toThrow(PaymentException::class);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe(100000.0);
});
