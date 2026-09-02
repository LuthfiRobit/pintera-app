<?php

use App\Domains\Keuangan\Actions\Tagihan\KoreksiNominalTagihanAction;
use App\Domains\Keuangan\Models\Tagihan;
use Database\Seeders\RolePermissionSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('applies corrected nominal and clears the flag when net_amount stays above paid_amount', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'discount_amount' => 0, 'net_amount' => 500000, 'paid_amount' => 100000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    app(KoreksiNominalTagihanAction::class)->execute($tagihan, 500000, 100000);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->total_tagihan)->toBe(500000.0);
    expect((float) $fresh->discount_amount)->toBe(100000.0);
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->status)->toBe('sebagian');
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
    expect($fresh->alasan_perlu_ditinjau)->toBeNull();
});

it('sets status to lunas automatically when the correction results in an overpayment', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'discount_amount' => 0, 'net_amount' => 500000, 'paid_amount' => 500000,
        'status' => 'sebagian', 'perlu_ditinjau_ulang' => true, 'alasan_perlu_ditinjau' => 'contoh',
    ]);

    // Koreksi: keringanan baru membuat net_amount seharusnya cuma 400.000, padahal sudah dibayar 500.000.
    app(KoreksiNominalTagihanAction::class)->execute($tagihan, 500000, 100000);

    $fresh = $tagihan->fresh();
    expect((float) $fresh->net_amount)->toBe(400000.0);
    expect($fresh->status)->toBe('lunas'); // TagihanStatusResolver: paid_amount(500rb) >= net_amount(400rb)
    expect($fresh->perlu_ditinjau_ulang)->toBeFalse();
});

it('rejects correcting a tagihan that is not currently flagged', function () {
    $tagihan = Tagihan::factory()->create([
        'total_tagihan' => 500000, 'net_amount' => 500000, 'paid_amount' => 0,
        'status' => 'belum_bayar', 'perlu_ditinjau_ulang' => false,
    ]);

    try {
        app(KoreksiNominalTagihanAction::class)->execute($tagihan, 400000, 0);
        test()->fail('Expected an HttpException with status 422 to be thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});
