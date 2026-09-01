<?php

use App\Domains\Identity\Actions\MergePersonsAction;
use App\Domains\Identity\Events\PersonsMerged;
use App\Domains\Identity\Models\Person;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

it('dispatches PersonsMerged synchronously when MergePersonsAction executes', function () {
    Event::fake([PersonsMerged::class]);

    $winning = Person::factory()->create();
    $losing = Person::factory()->create(['yayasan_id' => $winning->yayasan_id]);

    app(MergePersonsAction::class)->execute($losing, $winning);

    Event::assertDispatched(PersonsMerged::class, fn ($event) => $event->losing->id === $losing->id && $event->winning->id === $winning->id);
});

it('PersonsMerged does not implement ShouldQueue', function () {
    expect(PersonsMerged::class)->not->toImplement(ShouldQueue::class);
});
