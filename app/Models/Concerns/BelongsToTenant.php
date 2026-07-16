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

            if ($model->lembaga_id === null && $user) {
                // Auto-fill lembaga_id if user is lembaga-scoped (either via role or direct lembaga_id)
                if ($user->widestScopeLevel() === 'lembaga' || ($user->lembaga_id && $user->widestScopeLevel() !== 'yayasan')) {
                    $model->lembaga_id = $user->lembaga_id;
                }
            }
        });
    }
}
