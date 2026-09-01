<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Events\PersonsMerged;
use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Support\Facades\Event;

it('reparents tagihan.person_id from losing to winning when persons are merged', function () {
    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);
    $tagihan = Tagihan::factory()->create(['person_id' => $losing->id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    expect($tagihan->fresh()->person_id)->toBe($winning->id);
});

it('rolls back the entire merge, including the Person update, when the listener throws', function () {
    Event::listen(PersonsMerged::class, function () {
        throw new RuntimeException('simulated listener failure');
    });

    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);

    expect(fn () => app(MergePersonsAction::class)->execute($losing, $winning))
        ->toThrow(RuntimeException::class);

    expect($losing->fresh()->merged_into_person_id)->toBeNull();
    expect($losing->fresh()->trashed())->toBeFalse();
});
