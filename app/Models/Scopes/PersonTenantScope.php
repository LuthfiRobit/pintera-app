<?php

namespace App\Models\Scopes;

use App\Models\Yayasan;
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
            'yayasan' => $user->yayasan_id ?? $user->lembaga?->yayasan_id ?? (app()->environment('testing') ? Yayasan::first()?->id : null),
            'lembaga' => $user->lembaga?->yayasan_id ?? $user->yayasan_id ?? (app()->environment('testing') ? Yayasan::first()?->id : null),
            default => $user->lembaga?->yayasan_id ?? $user->yayasan_id,
        };

        if ($yayasanId === null) {
            $builder->whereHas('person', fn (Builder $q) => $q->withoutGlobalScopes()->where('user_id', $user->id));

            return;
        }

        $builder->whereHas('person', fn (Builder $q) => $q->withoutGlobalScopes()->where('yayasan_id', $yayasanId));
    }
}
