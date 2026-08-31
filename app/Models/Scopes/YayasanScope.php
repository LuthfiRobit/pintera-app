<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class YayasanScope implements Scope
{
    private static bool $resolvingActingUser = false;

    public function apply(Builder $builder, Model $model): void
    {
        // Same re-entrancy guard as TenantScope: the first cold resolution of
        // auth()->id() can recurse back into this scope via SessionGuard's
        // internal user() call. Breaking the cycle here mirrors TenantScope's
        // own guard exactly.
        if (self::$resolvingActingUser) {
            return;
        }

        self::$resolvingActingUser = true;

        try {
            $userId = auth()->id();
        } finally {
            self::$resolvingActingUser = false;
        }

        if (! $userId) {
            return;
        }

        $actingUser = User::withoutGlobalScope(TenantScope::class)->find($userId);

        if (! $actingUser) {
            return;
        }

        if ($actingUser->widestScopeLevel() === 'platform') {
            return;
        }

        $yayasanId = $actingUser->yayasan_id ?? $actingUser->lembaga?->yayasan_id;

        if (! $yayasanId) {
            // Fail closed: an actor with no resolvable yayasan boundary sees nothing,
            // matching TenantScope's own fail-closed philosophy elsewhere.
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.yayasan_id', $yayasanId);
    }
}
