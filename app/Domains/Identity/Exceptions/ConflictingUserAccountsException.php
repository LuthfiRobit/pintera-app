<?php

namespace App\Domains\Identity\Exceptions;

use App\Domains\Identity\Models\Person;
use RuntimeException;

class ConflictingUserAccountsException extends RuntimeException
{
    public function __construct(public readonly Person $losing, public readonly Person $winning)
    {
        parent::__construct(
            "Both Person #{$losing->id} and Person #{$winning->id} already have a linked user account. ".
            'An admin must explicitly choose which account survives before merging.'
        );
    }
}
