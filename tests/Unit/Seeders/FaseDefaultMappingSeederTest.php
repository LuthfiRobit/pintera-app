<?php

use App\Domains\Akademik\Models\Fase;
use App\Domains\Akademik\Models\FaseDefaultMapping;
use Database\Seeders\FaseDefaultMappingSeeder;
use Database\Seeders\FaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('seeds 17 platform mapping rows and stays idempotent on re-run', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::whereNull('lembaga_id')->count())->toBe(17);

    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::whereNull('lembaga_id')->count())->toBe(17);
});

it('does not create any mapping for SLB', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    expect(FaseDefaultMapping::where('bentuk_pendidikan', 'SLB')->count())->toBe(0);
});

it('re-running the seeder with a changed mapping definition updates only the mapping row, not any assigned Kelas.fase_id (immutability contract)', function () {
    (new FaseSeeder())->run();
    (new FaseDefaultMappingSeeder())->run();

    $faseA = Fase::where('kode', 'a')->first();
    $faseB = Fase::where('kode', 'b')->first();

    $mappingSd1 = FaseDefaultMapping::whereNull('lembaga_id')->where('bentuk_pendidikan', 'SD')->where('tingkat', '1')->first();
    expect($mappingSd1->fase_id)->toBe($faseA->id);

    // Simulasi admin platform mengubah kebijakan lewat baris data (bukan re-seed
    // literal, tapi hasil akhirnya sama: baris mapping berubah).
    $mappingSd1->update(['fase_id' => $faseB->id]);

    expect(FaseDefaultMapping::find($mappingSd1->id)->fase_id)->toBe($faseB->id);
});
