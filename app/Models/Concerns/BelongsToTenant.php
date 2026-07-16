<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            $user = auth()->user();

            if ($model->lembaga_id === null && $user && $user->widestScopeLevel() === 'lembaga') {
                $model->lembaga_id = $user->lembaga_id;
            }
        });
    }
}
