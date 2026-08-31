<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\Person;

class ReactivatePersonAction
{
    public function execute(Person $person): Person
    {
        $person->update(['deactivated_at' => null]);

        return $person;
    }
}
