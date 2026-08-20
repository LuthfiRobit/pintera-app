<?php

use App\Domains\Akademik\Actions\PolaJam\DuplicatePolaJamAction;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('duplicates a pola jam and all of its jam pelajaran slots', function () {
    $polaJam = PolaJam::factory()->create(['nama' => 'Pola Reguler']);
    JamPelajaran::factory()->count(3)->sequence(fn ($sq) => ['urutan' => $sq->index + 1])->create(['pola_jam_id' => $polaJam->id]);

    [$duplikat, $jumlahSlot] = (new DuplicatePolaJamAction)->execute($polaJam->fresh(['jamPelajaran']));

    expect($duplikat->nama)->toBe('Pola Reguler (Salinan)')
        ->and($jumlahSlot)->toBe(3)
        ->and($duplikat->jamPelajaran()->count())->toBe(3);
});
