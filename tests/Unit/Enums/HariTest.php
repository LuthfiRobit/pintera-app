<?php

use App\Enums\Hari;

it('defines all 7 days starting with Senin', function () {
    expect(array_column(Hari::cases(), 'value'))
        ->toBe(['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
});
