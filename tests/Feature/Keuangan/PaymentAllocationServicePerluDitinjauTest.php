<?php

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Domains\Keuangan\Services\PaymentAllocationService;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not allocate payment to a tagihan flagged perlu_ditinjau_ulang after the payment was already created', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0,
    ]);
    $pembayaran = Pembayaran::factory()->create(['status' => 'lunas']);
    PembayaranTagihan::create([
        'pembayaran_id' => $pembayaran->id,
        'tagihan_id' => $tagihan->id,
        'amount_allocated' => 100000,
    ]);

    // Simulasikan: tagihan di-flag SETELAH pembayaran QRIS dibuat, SEBELUM reconciliation
    // cron mengonfirmasi status PAID dan memanggil allocate() (persis skenario di finding).
    $tagihan->update(['perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh']);

    app(PaymentAllocationService::class)->allocate($pembayaran);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->paid_amount)->toBe(0.0);
    expect($fresh->status)->toBe('belum_bayar');
    expect($fresh->perlu_ditinjau_ulang)->toBeTrue();
});

it('still allocates normally to a non-flagged tagihan (regression guard)', function () {
    $siswa = Siswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'tagihable_id' => $siswa->id, 'tagihable_type' => Siswa::class,
        'status' => 'belum_bayar', 'total_tagihan' => 100000, 'net_amount' => 100000, 'paid_amount' => 0,
    ]);
    $pembayaran = Pembayaran::factory()->create(['status' => 'lunas']);
    PembayaranTagihan::create([
        'pembayaran_id' => $pembayaran->id,
        'tagihan_id' => $tagihan->id,
        'amount_allocated' => 100000,
    ]);

    app(PaymentAllocationService::class)->allocate($pembayaran);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->paid_amount)->toBe(100000.0);
    expect($fresh->status)->toBe('lunas');
});
