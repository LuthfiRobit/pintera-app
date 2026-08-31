<?php

namespace App\Models\Concerns;

use App\Models\Scopes\PersonTenantScope;

trait BelongsToTenantViaPerson
{
    public static function bootBelongsToTenantViaPerson(): void
    {
        static::addGlobalScope(new PersonTenantScope);
    }
}
