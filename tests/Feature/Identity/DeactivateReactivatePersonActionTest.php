<?php

use App\Domains\Identity\Actions\DeactivatePersonAction;
use App\Domains\Identity\Actions\ReactivatePersonAction;
use App\Domains\Identity\Models\Person;

it('sets deactivated_at without soft-deleting', function () {
    $person = Person::factory()->create();

    app(DeactivatePersonAction::class)->execute($person);

    expect($person->refresh()->deactivated_at)->not->toBeNull();
    expect(Person::find($person->id))->not->toBeNull();
});

it('clears deactivated_at on reactivate', function () {
    $person = Person::factory()->create(['deactivated_at' => now()]);

    app(ReactivatePersonAction::class)->execute($person);

    expect($person->refresh()->deactivated_at)->toBeNull();
});
