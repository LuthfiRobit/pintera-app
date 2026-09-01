<?php

use App\Domains\Keuangan\Enums\HariDalamMinggu;
use App\Domains\Keuangan\Enums\TipeTagihan;

it('has a label for every TipeTagihan case', function () {
    expect(TipeTagihan::Harian->label())->toBe('Harian');
    expect(TipeTagihan::Mingguan->label())->toBe('Mingguan');
    expect(TipeTagihan::Bulanan->label())->toBe('Bulanan');
    expect(TipeTagihan::Tahunan->label())->toBe('Tahunan');
    expect(TipeTagihan::Sekali->label())->toBe('Sekali');
});

it('maps HariDalamMinggu cases to ISO weekday integers matching Carbon::dayOfWeekIso', function () {
    expect(HariDalamMinggu::Senin->value)->toBe(1);
    expect(HariDalamMinggu::Minggu->value)->toBe(7);
});
