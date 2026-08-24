<?php

use App\Domains\Keuangan\Models\Pembayaran;
use App\Domains\Keuangan\Models\PembayaranTagihan;
use App\Domains\Keuangan\Models\Tagihan;

it('lets a single pembayaran allocate its amount across multiple tagihan rows', function () {
    $pembayaran = Pembayaran::factory()->create();
    $tagihanSatu = Tagihan::factory()->create();
    $tagihanDua = Tagihan::factory()->create();

    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihanSatu->id, 'amount_allocated' => 100000]);
    PembayaranTagihan::create(['pembayaran_id' => $pembayaran->id, 'tagihan_id' => $tagihanDua->id, 'amount_allocated' => 50000]);

    $alokasi = PembayaranTagihan::where('pembayaran_id', $pembayaran->id)->get();
    expect($alokasi)->toHaveCount(2);
    expect((float) $alokasi->sum('amount_allocated'))->toBe(150000.0);
});
