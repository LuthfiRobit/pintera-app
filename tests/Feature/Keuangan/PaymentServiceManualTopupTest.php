<?php
// tests/Feature/Keuangan/PaymentServiceManualTopupTest.php

use App\Models\ManualPaymentRequest;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Finance\PaymentService;

it('creates a manual topup payment with topup_status pending and no pembayaran_tagihan rows', function () {
    $siswa = Siswa::factory()->create();
    $user = User::factory()->create();

    $pembayaran = app(PaymentService::class)->createManualTopupPayment($siswa, [
        'amount' => 250000,
        'requested_by' => $user->id,
        'transfer_proof_path' => 'proofs/test.jpg',
        'bank_origin' => 'BCA',
        'transfer_date' => now()->toDateString(),
    ]);

    expect($pembayaran->metode)->toBe('transfer_manual');
    expect($pembayaran->status)->toBe('menunggu_verifikasi');
    expect((float) $pembayaran->amount)->toBe(250000.0);
    expect($pembayaran->topup_status)->toBe('pending');
    expect($pembayaran->siswa_id)->toBe($siswa->id);
    expect($pembayaran->pembayaranTagihan()->count())->toBe(0);

    $manualRequest = ManualPaymentRequest::where('pembayaran_id', $pembayaran->id)->first();
    expect($manualRequest)->not->toBeNull();
    expect($manualRequest->status)->toBe('PENDING');
    expect((float) $manualRequest->amount)->toBe(250000.0);
});
