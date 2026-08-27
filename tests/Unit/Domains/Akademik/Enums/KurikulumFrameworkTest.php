<?php

use App\Domains\Akademik\Enums\KurikulumFramework;

it('has exactly two cases: K13 and Merdeka', function () {
    expect(KurikulumFramework::cases())->toHaveCount(2);
    expect(KurikulumFramework::from('k13'))->toBe(KurikulumFramework::K13);
    expect(KurikulumFramework::from('merdeka'))->toBe(KurikulumFramework::Merdeka);
});

it('labels each case in Indonesian', function () {
    expect(KurikulumFramework::K13->label())->toBe('Kurikulum 2013 (K13)');
    expect(KurikulumFramework::Merdeka->label())->toBe('Kurikulum Merdeka');
});
