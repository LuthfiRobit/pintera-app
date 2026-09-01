<?php

use App\Domains\Identity\Models\Person;
use App\Domains\Keuangan\Models\Tagihan;
use Illuminate\Database\QueryException;

it('rejects a null person_id at the database level', function () {
    $this->expectException(QueryException::class);

    Tagihan::factory()->create(['person_id' => null]);
});

it('restricts deleting a person that still has tagihan rows', function () {
    $person = Person::factory()->create();
    Tagihan::factory()->create(['person_id' => $person->id]);

    $this->expectException(QueryException::class);

    $person->forceDelete();
});
