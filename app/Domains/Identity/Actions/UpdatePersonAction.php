<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Person;

class UpdatePersonAction
{
    /** @param array<string, mixed> $identityData */
    public function execute(Person $person, array $identityData): Person
    {
        unset($identityData['yayasan_id']);

        $person->update($identityData);

        return $person;
    }
}
