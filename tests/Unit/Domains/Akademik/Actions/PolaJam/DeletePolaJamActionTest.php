<?php

use App\Domains\Akademik\Actions\PolaJam\DeletePolaJamAction;
use App\Domains\Akademik\Models\JamPelajaran;
use App\Domains\Akademik\Models\PolaJam;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('deletes a pola jam with no dependents', function () {
    $polaJam = PolaJam::factory()->create();

    (new DeletePolaJamAction)->execute($polaJam);

    expect(PolaJam::find($polaJam->id))->toBeNull();
});

it('refuses to delete a pola jam still linked to a kelas', function () {
    $polaJam = PolaJam::factory()->create();
    Kelas::factory()->create(['pola_jam_id' => $polaJam->id]);

    expect(fn () => (new DeletePolaJamAction)->execute($polaJam))
        ->toThrow(ValidationException::class);
    expect(PolaJam::find($polaJam->id))->not->toBeNull();
});

it('refuses to delete a pola jam whose jam pelajaran is already used in jadwal pelajaran', function () {
    $polaJam = PolaJam::factory()->has(
        JamPelajaran::factory()->count(1),
        'jamPelajaran'
    )->create();
    $jamPelajaran = $polaJam->jamPelajaran->first();
    JadwalPelajaran::factory()->create(['jam_pelajaran_id' => $jamPelajaran->id]);

    expect(fn () => (new DeletePolaJamAction)->execute($polaJam))
        ->toThrow(ValidationException::class);
});
