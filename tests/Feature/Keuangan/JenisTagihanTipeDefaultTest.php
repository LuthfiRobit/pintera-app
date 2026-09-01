<?php

use App\Domains\Keuangan\Enums\TipeTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;

it('defaults tipe to bulanan when not specified, regardless of mode, so existing tests/factories keep working', function () {
    $manual = JenisTagihan::factory()->create(['mode' => 'manual']);
    $otomatis = JenisTagihan::factory()->create(['mode' => 'otomatis']);

    expect($manual->fresh()->tipe)->toBe(TipeTagihan::Bulanan);
    expect($otomatis->fresh()->tipe)->toBe(TipeTagihan::Bulanan);
});
