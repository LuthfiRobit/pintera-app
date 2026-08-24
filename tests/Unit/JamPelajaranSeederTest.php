<?php
// tests/Unit/JamPelajaranSeederTest.php

use App\Domains\Akademik\Models\JamPelajaran;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\Yayasan;
use Database\Seeders\JamPelajaranSeeder;
use Database\Seeders\LembagaSeeder;
use Database\Seeders\PolaJamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Yayasan::factory()->create();
    (new LembagaSeeder())->run();
    (new PolaJamSeeder())->run();
});

it('seeds appropriate time slots across 7 days for the SD institution', function () {
    (new JamPelajaranSeeder())->run();

    foreach (Lembaga::all() as $lembaga) {
        $polaJam = PolaJam::where('lembaga_id', $lembaga->id)->first();
        $expectedCountPerDay = match ($lembaga->bentuk_pendidikan) {
            'SD' => 7,       // 6 pelajaran + 1 istirahat
            default => 7,
        };

        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'] as $hari) {
            $slots = JamPelajaran::where('pola_jam_id', $polaJam->id)->where('hari', $hari)->get();
            expect($slots)->toHaveCount($expectedCountPerDay);
            expect($slots->where('is_pelajaran', false)->count())->toBe(1); // 1 istirahat per hari
        }
    }
});

it('is idempotent when run twice', function () {
    (new JamPelajaranSeeder())->run();
    (new JamPelajaranSeeder())->run();

    // 7 days * 7 = 49 rows
    expect(JamPelajaran::count())->toBe(49);
});
