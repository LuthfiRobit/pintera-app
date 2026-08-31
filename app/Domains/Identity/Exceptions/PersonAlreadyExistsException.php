<?php

namespace App\Domains\Identity\Exceptions;

use App\Domains\Identity\Models\Person;
use RuntimeException;

class PersonAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly Person $existing)
    {
        parent::__construct("Person with this NIK already exists in this yayasan (person_id={$existing->id}).");
    }
}
