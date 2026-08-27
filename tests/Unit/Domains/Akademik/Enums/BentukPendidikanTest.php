<?php

use App\Domains\Akademik\Enums\BentukPendidikan;

it('has exactly the 9 bentuk_pendidikan values from the lembaga ENUM column', function () {
    $values = array_map(fn ($c) => $c->value, BentukPendidikan::cases());
    expect($values)->toEqualCanonicalizing(['KB', 'TPA', 'SPS', 'TK', 'SD', 'SMP', 'SMA', 'SMK', 'SLB']);
});

it('returns A/B for PAUD-type bentuk pendidikan', function () {
    expect(BentukPendidikan::Kb->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Tpa->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Sps->validTingkatValues())->toBe(['A', 'B']);
    expect(BentukPendidikan::Tk->validTingkatValues())->toBe(['A', 'B']);
});

it('returns 1-6 for SD and SLB', function () {
    expect(BentukPendidikan::Sd->validTingkatValues())->toBe(['1', '2', '3', '4', '5', '6']);
    expect(BentukPendidikan::Slb->validTingkatValues())->toBe(['1', '2', '3', '4', '5', '6']);
});

it('returns 7-9 for SMP', function () {
    expect(BentukPendidikan::Smp->validTingkatValues())->toBe(['7', '8', '9']);
});

it('returns 10-12 for SMA and SMK', function () {
    expect(BentukPendidikan::Sma->validTingkatValues())->toBe(['10', '11', '12']);
    expect(BentukPendidikan::Smk->validTingkatValues())->toBe(['10', '11', '12']);
});
