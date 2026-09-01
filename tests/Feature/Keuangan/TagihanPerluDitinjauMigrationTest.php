<?php

use App\Domains\Keuangan\Models\Tagihan;

it('has perlu_ditinjau_ulang defaulting to false and alasan_perlu_ditinjau nullable', function () {
    $tagihan = Tagihan::factory()->create();

    expect($tagihan->fresh()->perlu_ditinjau_ulang)->toBeFalse();
    expect($tagihan->fresh()->alasan_perlu_ditinjau)->toBeNull();
});
