<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class PersonTenantScope implements Scope
{
    private static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolving) {
            return;
        }

        self::$resolving = true;
        $user = auth()->user();
        self::$resolving = false;

        if ($user === null) {
            return;
        }

        $widestScopeLevel = $user->widestScopeLevel();

        if ($widestScopeLevel === 'platform') {
            return;
        }

        $yayasanId = match ($widestScopeLevel) {
            'yayasan' => $user->yayasan_id,
            default => $user->lembaga?->yayasan_id,
        };

        if ($yayasanId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->whereHas('person', fn (Builder $q) => $q->withoutGlobalScopes()->where('yayasan_id', $yayasanId));
    }
}
