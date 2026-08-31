<?php

use App\Domains\Identity\Models\Person;
use App\Models\Guru;
use App\Models\Lembaga;

it('fails when a guru row still has no person_id', function () {
    $lembaga = Lembaga::factory()->create();
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => null]);

    $this->artisan('identity:verify-backfill')
        ->expectsOutputToContain('guru')
        ->assertExitCode(1);
});

it('succeeds when every role table row has a person_id', function () {
    $lembaga = Lembaga::factory()->create();
    Guru::factory()->create(['lembaga_id' => $lembaga->id, 'person_id' => Person::factory()->create(['yayasan_id' => $lembaga->yayasan_id])->id]);

    $this->artisan('identity:verify-backfill')->assertExitCode(0);
});
