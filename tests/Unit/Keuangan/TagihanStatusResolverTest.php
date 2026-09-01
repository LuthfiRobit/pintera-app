<?php

use App\Domains\Keuangan\Services\TagihanStatusResolver;

it('resolves lunas when paid_amount covers net_amount', function () {
    expect((new TagihanStatusResolver)->resolve(500000, 500000, 'sebagian'))->toBe('lunas');
    expect((new TagihanStatusResolver)->resolve(600000, 500000, 'sebagian'))->toBe('lunas');
});

it('resolves sebagian when paid_amount is positive but below net_amount', function () {
    expect((new TagihanStatusResolver)->resolve(100000, 500000, 'belum_bayar'))->toBe('sebagian');
});

it('resolves belum_bayar when paid_amount is zero', function () {
    expect((new TagihanStatusResolver)->resolve(0, 500000, 'belum_bayar'))->toBe('belum_bayar');
});

it('preserves dibatalkan regardless of amounts', function () {
    expect((new TagihanStatusResolver)->resolve(500000, 500000, 'dibatalkan'))->toBe('dibatalkan');
    expect((new TagihanStatusResolver)->resolve(0, 500000, 'dibatalkan'))->toBe('dibatalkan');
});
