<?php

use App\Enums\Hari;

it('defines all 7 days starting with Senin', function () {
    expect(array_column(Hari::cases(), 'value'))
        ->toBe(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
});

it('returns only the active-day cases, excluding the given off-days', function () {
    // Off: Sunday (0) and Friday (5) — matches the pesantren example from Tahap 3b's design discussion
    $aktif = Hari::aktifDari([0, 5]);

    expect(array_column($aktif, 'value'))->toBe(['senin', 'selasa', 'rabu', 'kamis', 'sabtu']);
});

it('returns all 7 cases when no days are off', function () {
    expect(Hari::aktifDari([]))->toHaveCount(7);
});
