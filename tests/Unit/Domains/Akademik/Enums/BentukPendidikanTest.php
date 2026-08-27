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

it('treats KB tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Kb->isTingkatAkhir('B'))->toBeFalse();
});

it('treats TPA tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Tpa->isTingkatAkhir('B'))->toBeFalse();
});

it('treats SPS tingkat B as NOT tingkat akhir (permanent exclusion, Priority #3)', function () {
    expect(BentukPendidikan::Sps->isTingkatAkhir('B'))->toBeFalse();
});

it('treats TK tingkat B as tingkat akhir', function () {
    expect(BentukPendidikan::Tk->isTingkatAkhir('B'))->toBeTrue();
});

it('treats TK tingkat A as NOT tingkat akhir', function () {
    expect(BentukPendidikan::Tk->isTingkatAkhir('A'))->toBeFalse();
});

it('treats SD tingkat 6 as tingkat akhir, tingkat 5 as not', function () {
    expect(BentukPendidikan::Sd->isTingkatAkhir('6'))->toBeTrue();
    expect(BentukPendidikan::Sd->isTingkatAkhir('5'))->toBeFalse();
});

it('treats SLB tingkat 6 as tingkat akhir', function () {
    expect(BentukPendidikan::Slb->isTingkatAkhir('6'))->toBeTrue();
});

it('treats SMP tingkat 9 as tingkat akhir, tingkat 8 as not', function () {
    expect(BentukPendidikan::Smp->isTingkatAkhir('9'))->toBeTrue();
    expect(BentukPendidikan::Smp->isTingkatAkhir('8'))->toBeFalse();
});

it('treats SMA tingkat 12 as tingkat akhir, tingkat 11 as not', function () {
    expect(BentukPendidikan::Sma->isTingkatAkhir('12'))->toBeTrue();
    expect(BentukPendidikan::Sma->isTingkatAkhir('11'))->toBeFalse();
});

it('treats SMK tingkat 12 as tingkat akhir', function () {
    expect(BentukPendidikan::Smk->isTingkatAkhir('12'))->toBeTrue();
});

it('treats null tingkat as NOT tingkat akhir for every case', function () {
    foreach (BentukPendidikan::cases() as $case) {
        expect($case->isTingkatAkhir(null))->toBeFalse();
    }
});
