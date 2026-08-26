<?php

use App\Domains\Akademik\Models\Fase;
use Database\Seeders\FaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly 7 fase rows and stays idempotent on re-run', function () {
    (new FaseSeeder())->run();
    expect(Fase::count())->toBe(7);

    (new FaseSeeder())->run();
    expect(Fase::count())->toBe(7);

    expect(Fase::where('kode', 'foundation')->first()->urutan)->toBe(0);
    expect(Fase::where('kode', 'f')->first()->urutan)->toBe(6);
});
