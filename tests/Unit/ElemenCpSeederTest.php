<?php

use App\Domains\Akademik\Models\ElemenCp;
use Database\Seeders\ElemenCpSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds exactly 3 elemen cp in the correct fixed order', function () {
    (new ElemenCpSeeder())->run();

    expect(ElemenCp::count())->toBe(3);
    expect(ElemenCp::orderBy('no_urut')->pluck('kode')->all())
        ->toBe(['nilai_agama_moral', 'jati_diri', 'literasi_steam']);
});

it('is idempotent when run twice', function () {
    (new ElemenCpSeeder())->run();
    (new ElemenCpSeeder())->run();

    expect(ElemenCp::count())->toBe(3);
});
