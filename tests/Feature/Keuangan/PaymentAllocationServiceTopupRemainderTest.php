<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Models\Siswa;
use App\Domains\Keuangan\Models\Tagihan;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatPembayaranGabungan(Siswa $siswa, float $tagihanAmount, float $sisaTopup, string $topupStatus = 'pending'): Pembayaran
{
    $jenis = JenisTagihan::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class, 'jenis_tagihan_id' => $jenis->id,
        'status' => 'lunas', 'net_amount' => $tagihanAmount, 'paid_amount' => $tagihanAmount,
    ]);

    $pembayaran = Pembayaran::create([
        'siswa_id' => $siswa->id, 'metode' => 'qris', 'status' => 'lunas',
        'amount' => $tagihanAmount + $sisaTopup, 'topup_status' => $topupStatus,
        'channel_reference' => (string) \Illuminate\Support\Str::uuid(),
    ]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihan->id, 'amount_allocated' => $tagihanAmount]);

    return $pembayaran;
}

it('tops up the wallet with exactly the remainder after the tagihan allocation', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000);
    $saldoAwal = (float) $siswa->wallet->balance;

    app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe($saldoAwal + 50000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});

it('is a no-op when topup_status is none', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000, topupStatus: 'none');
    $saldoAwal = (float) $siswa->wallet->balance;

    app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);

    expect((float) $siswa->wallet->fresh()->balance)->toBe($saldoAwal);
    expect($pembayaran->fresh()->topup_status)->toBe('none');
});

it('is a no-op when topup_status is already completed (idempotent)', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000, topupStatus: 'completed');
    $saldoAwal = (float) $siswa->wallet->balance;

    app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);

    expect((float) $siswa->wallet->fresh()->balance)->toBe($saldoAwal);
});

it('marks topup_status failed when the wallet cannot be found', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000);
    $siswa->wallet->delete();

    app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);

    expect($pembayaran->fresh()->topup_status)->toBe('failed');
});

it('retries a previously failed topup and marks it completed', function () {
    $siswa = Siswa::factory()->create();
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000, topupStatus: 'failed');
    $saldoAwal = (float) $siswa->wallet->balance;

    app(PaymentAllocationService::class)->topupSisaJikaAda($pembayaran);

    $siswa->wallet->refresh();
    expect((float) $siswa->wallet->balance)->toBe($saldoAwal + 50000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});

it('credits the wallet exactly once, marking topup_status completed, when AutoAllocationEngine::run() throws on every repeated retryFailedTopups()-style re-run (round-3 double-credit regression)', function () {
    $siswa = Siswa::factory()->create();
    \App\Models\SystemSetting::create(['lembaga_id' => $siswa->lembaga_id, 'key' => 'auto_debit_enabled', 'value' => 'true']);
    $pembayaran = buatPembayaranGabungan($siswa, tagihanAmount: 100000, sisaTopup: 50000);
    $saldoAwal = (float) $siswa->wallet->balance;

    $mockEngine = \Mockery::mock(\App\Services\Finance\AutoAllocationEngine::class);
    $mockEngine->shouldReceive('run')->andThrow(new \RuntimeException('Simulated AutoAllocationEngine failure'));
    app()->instance(\App\Services\Finance\AutoAllocationEngine::class, $mockEngine);

    \Illuminate\Support\Facades\Log::shouldReceive('error')->atLeast()->once();

    $service = app(PaymentAllocationService::class);

    // First run: wallet balance is credited, but the subsequent auto-allocation
    // step throws. topup_status must land on 'completed' (not 'failed'), so a
    // real hourly ReconcilePayments::retryFailedTopups() run would never
    // re-select this Pembayaran in the first place.
    $service->topupSisaJikaAda($pembayaran);
    $balanceAfterFirst = (float) $siswa->wallet->fresh()->balance;
    expect($balanceAfterFirst)->toBe($saldoAwal + 50000.0);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');

    // Simulate the scheduler running again (and again) against the same row --
    // must be a safe no-op every time, not a repeated credit.
    $service->topupSisaJikaAda($pembayaran->fresh());
    $service->topupSisaJikaAda($pembayaran->fresh());

    expect((float) $siswa->wallet->fresh()->balance)->toBe($balanceAfterFirst);
    expect($pembayaran->fresh()->topup_status)->toBe('completed');
});
